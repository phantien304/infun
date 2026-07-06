<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Entities\ProductStock;
use App\Models\Entities\StockReservation;
use App\Services\ConfigDbService;
use App\Services\Stock\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kiểm chứng 3 lớp chống oversell: GUARD trong lock, RESERVATION (giữ chỗ) và
 * việc nhả hold hết hạn.
 *
 * Test tự dựng schema tối thiểu (product_stock, stock_movement, stock_reservation)
 * trong setUp nên KHÔNG phụ thuộc toàn bộ chuỗi migration của dự án. Ghi đè
 * ConfigDbService để bật config_stock_checkout mà không cần bảng settings.
 *
 * Lưu ý: model Base sinh id qua auto-increment của DB, chạy tốt nhất trên MySQL
 * (như suite thật của dự án). Ở đây dùng id tường minh cho các row setup.
 */
class StockOversellTest extends TestCase
{
    protected StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        // Bật kiểm tra tồn (config_stock_checkout = 1) mà không cần bảng settings.
        $this->app->bind(ConfigDbService::class, function () {
            return new class extends ConfigDbService {
                public function __construct() {}

                public function getConfig()
                {
                    return ['config_stock_checkout' => 1];
                }
            };
        });

        $this->createStockSchema();
        $this->stock = $this->app->make(StockService::class);
    }

    private function createStockSchema(): void
    {
        Schema::create('product_stock', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_variant_id');
            $t->unsignedBigInteger('warehouse_id')->default(1);
            $t->integer('on_hand')->default(0);
            $t->integer('reserved')->default(0);
            $t->boolean('subtract')->default(true);
            $t->unsignedTinyInteger('inventory_policy')->default(0);
            $t->unsignedBigInteger('version')->default(0);
            $t->timestamps();
            $t->unique(['product_variant_id', 'warehouse_id']);
        });

        Schema::create('stock_movement', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_variant_id');
            $t->unsignedBigInteger('warehouse_id')->default(1);
            $t->string('type', 32);
            $t->integer('quantity_change');
            $t->integer('on_hand_after');
            $t->string('reference_type', 32)->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('note', 255)->nullable();
        });

        Schema::create('stock_reservation', function ($t) {
            $t->bigIncrements('id');
            $t->string('holder', 191);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('product_variant_id');
            $t->unsignedBigInteger('warehouse_id')->default(1);
            $t->integer('quantity')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['holder', 'product_variant_id', 'warehouse_id']);
        });
    }

    private function seedStock(int $variantId, int $onHand, int $policy = 0, int $reserved = 0): void
    {
        DB::table('product_stock')->insert([
            'id'                 => $variantId,
            'product_variant_id' => $variantId,
            'warehouse_id'       => 1,
            'on_hand'            => $onHand,
            'reserved'           => $reserved,
            'subtract'           => true,
            'inventory_policy'   => $policy,
            'version'            => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function onHand(int $variantId): int
    {
        return (int) DB::table('product_stock')->where('product_variant_id', $variantId)->value('on_hand');
    }

    private function reserved(int $variantId): int
    {
        return (int) DB::table('product_stock')->where('product_variant_id', $variantId)->value('reserved');
    }

    // ---------------------------------------------------------------------
    // GUARD
    // ---------------------------------------------------------------------

    public function test_guard_blocks_oversell_and_rolls_back(): void
    {
        $this->seedStock(variantId: 1, onHand: 3, policy: 0); // DENY

        $this->expectException(InsufficientStockException::class);

        try {
            DB::transaction(function () {
                $this->stock->deductForOrder(
                    ['product_variant_id' => 1, 'quantity' => 5, 'order_id' => 10],
                    'holderA',
                    null,
                );
            });
        } finally {
            // Rollback giữ nguyên tồn — không bị đẩy âm.
            $this->assertSame(3, $this->onHand(1));
        }
    }

    public function test_guard_allows_exact_stock(): void
    {
        $this->seedStock(variantId: 1, onHand: 3, policy: 0);

        DB::transaction(function () {
            $this->stock->deductForOrder(
                ['product_variant_id' => 1, 'quantity' => 3, 'order_id' => 10],
                'holderA',
                null,
            );
        });

        $this->assertSame(0, $this->onHand(1));
    }

    public function test_backorder_policy_allows_negative(): void
    {
        $this->seedStock(variantId: 1, onHand: 2, policy: 1); // BACKORDER

        DB::transaction(function () {
            $this->stock->deductForOrder(
                ['product_variant_id' => 1, 'quantity' => 5, 'order_id' => 10],
                'holderA',
                null,
            );
        });

        $this->assertSame(-3, $this->onHand(1));
    }

    // ---------------------------------------------------------------------
    // RESERVATION
    // ---------------------------------------------------------------------

    public function test_reservation_reduces_reserved_and_blocks_others(): void
    {
        $this->seedStock(variantId: 1, onHand: 1, policy: 0);

        $ok = $this->stock->reserveCart(
            [['product_variant_id' => 1, 'quantity' => 1, 'name' => 'SP']],
            'holderA',
            null,
        );
        $this->assertTrue($ok['ok']);
        $this->assertSame(1, $this->reserved(1));

        // Người khác không giữ được nữa.
        $fail = $this->stock->reserveCart(
            [['product_variant_id' => 1, 'quantity' => 1, 'name' => 'SP']],
            'holderB',
            null,
        );
        $this->assertFalse($fail['ok']);
        $this->assertSame(0, (int) ($fail['failed']['available'] ?? -1));

        // Chính chủ vẫn "thấy" hàng: holderReservedMap cộng ngược phần đang giữ.
        $map = $this->stock->holderReservedMap('holderA');
        $this->assertSame(1, $map[1] ?? 0);
    }

    public function test_reservation_is_idempotent_on_refresh(): void
    {
        $this->seedStock(variantId: 1, onHand: 5, policy: 0);

        $this->stock->reserveCart([['product_variant_id' => 1, 'quantity' => 2, 'name' => 'SP']], 'holderA', null);
        $this->stock->reserveCart([['product_variant_id' => 1, 'quantity' => 2, 'name' => 'SP']], 'holderA', null);

        // Giữ lại trang không cộng dồn: reserved vẫn = 2, chỉ 1 row hold.
        $this->assertSame(2, $this->reserved(1));
        $this->assertSame(1, StockReservation::where('holder', 'holderA')->count());
    }

    public function test_order_consumes_hold_keeping_stock_consistent(): void
    {
        $this->seedStock(variantId: 1, onHand: 1, policy: 0);
        $this->stock->reserveCart([['product_variant_id' => 1, 'quantity' => 1, 'name' => 'SP']], 'holderA', null);
        $this->assertSame(1, $this->reserved(1));

        DB::transaction(function () {
            $this->stock->deductForOrder(
                ['product_variant_id' => 1, 'quantity' => 1, 'order_id' => 10],
                'holderA',
                null,
            );
        });

        $this->assertSame(0, $this->onHand(1));
        $this->assertSame(0, $this->reserved(1));
        $this->assertSame(0, StockReservation::where('holder', 'holderA')->count());
    }

    public function test_release_expired_restores_sellable(): void
    {
        $this->seedStock(variantId: 1, onHand: 5, policy: 0);
        $this->stock->reserveCart([['product_variant_id' => 1, 'quantity' => 3, 'name' => 'SP']], 'holderA', null);
        $this->assertSame(3, $this->reserved(1));

        // Ép hết hạn rồi chạy dọn.
        StockReservation::where('holder', 'holderA')->update(['expires_at' => Carbon::now()->subMinute()]);
        $released = $this->stock->releaseExpired();

        $this->assertSame(1, $released);
        $this->assertSame(0, $this->reserved(1));
        $this->assertSame(0, StockReservation::where('holder', 'holderA')->count());
    }
}
