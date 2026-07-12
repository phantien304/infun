<?php

namespace Tests\Feature\Stock;

use App\Enums\StockMovementType;
use App\Services\ConfigDbService;
use App\Services\Stock\StockService;
use App\Services\Stock\WarehouseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature test cho các nhánh reserve/deduct CHƯA được StockOversellTest phủ:
 *   - reserveCart: tắt kiểm tra tồn, variant không tracking, policy backorder
 *     bypass, phân bổ nhiều kho;
 *   - deductForOrder: thiếu product_variant_id, không có row kho, policy
 *     Untracked (chỉ nhả hold), và ghi movement SaleBackorder cho phần thiếu.
 *
 * Tự dựng schema tối thiểu (3 bảng stock) như StockOversellTest, cộng thêm:
 *   - Override ConfigDbService::getConfigs() (ĐÚNG tên method thật) để bật/tắt
 *     config_stock_checkout mà không cần bảng settings.
 *   - Bind WarehouseService giả (sellableIds/defaultId cố định) để không phụ
 *     thuộc bảng warehouse + tầng cache repo.
 *
 * Mỗi test chạy trên 1 sqlite :memory: mới (app refresh giữa các test).
 */
class StockReserveDeductTest extends TestCase
{
    protected StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindConfig(stockCheckout: 1);
        $this->bindWarehouse();
        $this->createStockSchema();

        $this->stock = $this->app->make(StockService::class);
    }

    protected function bindConfig(int $stockCheckout): void
    {
        $this->app->instance(ConfigDbService::class, new class($stockCheckout) extends ConfigDbService
        {
            public function __construct(private int $sc)
            {
            }

            public function getConfigs(): array
            {
                return ['config_stock_checkout' => $this->sc];
            }
        });
    }

    protected function bindWarehouse(): void
    {
        // Hai kho bán được: id 1 (mặc định) ưu tiên trước, rồi id 2.
        $this->app->instance(WarehouseService::class, new class extends WarehouseService
        {
            public function __construct()
            {
            }

            public function sellableIds(): array
            {
                return [1, 2];
            }

            public function defaultId(): int
            {
                return 1;
            }
        });
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

    private function seedStock(int $id, int $variantId, int $warehouseId, int $onHand, int $policy = 0, int $reserved = 0): void
    {
        DB::table('product_stock')->insert([
            'id'                 => $id,
            'product_variant_id' => $variantId,
            'warehouse_id'       => $warehouseId,
            'on_hand'            => $onHand,
            'reserved'           => $reserved,
            'subtract'           => true,
            'inventory_policy'   => $policy,
            'version'            => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function onHand(int $variantId, int $warehouseId = 1): int
    {
        return (int) DB::table('product_stock')
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->value('on_hand');
    }

    private function reserved(int $variantId, int $warehouseId = 1): int
    {
        return (int) DB::table('product_stock')
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->value('reserved');
    }

    // ---------------------------------------------------------------- reserve

    public function test_reserve_bo_qua_khi_tat_kiem_tra_ton(): void
    {
        $this->bindConfig(stockCheckout: 0);
        $this->seedStock(1, variantId: 1, warehouseId: 1, onHand: 1);

        $res = $this->stock->reserveCart(
            [['product_variant_id' => 1, 'quantity' => 5, 'name' => 'SP']],
            'holderA',
            null,
        );

        $this->assertTrue($res['ok']);
        $this->assertSame(0, $this->reserved(1)); // không tạo giữ chỗ
    }

    public function test_reserve_variant_khong_co_row_ton_van_ok(): void
    {
        // variant 99 không có product_stock -> reserveOne trả ok (không tracking).
        $res = $this->stock->reserveCart(
            [['product_variant_id' => 99, 'quantity' => 3, 'name' => 'SP']],
            'holderA',
            null,
        );

        $this->assertTrue($res['ok']);
    }

    public function test_reserve_backorder_bypass_khong_giu_cho(): void
    {
        $this->seedStock(1, variantId: 1, warehouseId: 1, onHand: 1, policy: 1); // BACKORDER

        $res = $this->stock->reserveCart(
            [['product_variant_id' => 1, 'quantity' => 10, 'name' => 'SP']],
            'holderA',
            null,
        );

        $this->assertTrue($res['ok']);
        $this->assertSame(0, $this->reserved(1)); // bypass -> không đụng reserved
    }

    public function test_reserve_phan_bo_qua_nhieu_kho(): void
    {
        $this->seedStock(1, variantId: 1, warehouseId: 1, onHand: 2, policy: 0);
        $this->seedStock(2, variantId: 1, warehouseId: 2, onHand: 3, policy: 0);

        $res = $this->stock->reserveCart(
            [['product_variant_id' => 1, 'quantity' => 4, 'name' => 'SP']],
            'holderA',
            null,
        );

        $this->assertTrue($res['ok']);
        $this->assertSame(2, $this->reserved(1, 1)); // kho 1 giữ hết 2
        $this->assertSame(2, $this->reserved(1, 2)); // kho 2 giữ phần còn lại
    }

    // ----------------------------------------------------------------- deduct

    public function test_deduct_thieu_variant_id_khong_thay_doi_ton(): void
    {
        $this->seedStock(1, variantId: 1, warehouseId: 1, onHand: 5);

        // Không có product_variant_id -> chỉ log, không trừ.
        $this->stock->deductForOrder(
            ['id' => 'SKU-X', 'quantity' => 2, 'order_id' => 10],
            'holderA',
            null,
        );

        $this->assertSame(5, $this->onHand(1));
    }

    public function test_deduct_khong_co_row_ton_khong_nem_loi(): void
    {
        $this->stock->deductForOrder(
            ['product_variant_id' => 99, 'quantity' => 2, 'order_id' => 10],
            'holderA',
            null,
        );

        $this->assertSame(0, DB::table('product_stock')->where('product_variant_id', 99)->count());
    }

    public function test_deduct_untracked_chi_nha_hold_khong_tru_ton(): void
    {
        $this->seedStock(1, variantId: 1, warehouseId: 1, onHand: 5, policy: 2, reserved: 3); // UNTRACKED
        DB::table('stock_reservation')->insert([
            'holder'             => 'holderA',
            'product_variant_id' => 1,
            'warehouse_id'       => 1,
            'quantity'           => 3,
            'expires_at'         => now()->addHour(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        DB::transaction(function () {
            $this->stock->deductForOrder(
                ['product_variant_id' => 1, 'quantity' => 2, 'order_id' => 10],
                'holderA',
                null,
            );
        });

        $this->assertSame(5, $this->onHand(1));                 // on_hand không đổi
        $this->assertSame(0, $this->reserved(1));               // hold được nhả
        $this->assertSame(0, DB::table('stock_reservation')->where('holder', 'holderA')->count());
    }

    public function test_deduct_backorder_ghi_movement_sale_backorder(): void
    {
        $this->seedStock(1, variantId: 1, warehouseId: 1, onHand: 2, policy: 1); // BACKORDER

        DB::transaction(function () {
            $this->stock->deductForOrder(
                ['product_variant_id' => 1, 'quantity' => 5, 'order_id' => 10],
                'holderA',
                null,
            );
        });

        $this->assertSame(-3, $this->onHand(1)); // 2 tồn - 5 = -3

        $this->assertTrue(
            DB::table('stock_movement')
                ->where('product_variant_id', 1)
                ->where('type', StockMovementType::SaleBackorder->value)
                ->where('quantity_change', -3)
                ->exists(),
            'Phần vượt tồn phải ghi movement loại sale_backorder (-3).',
        );
        $this->assertTrue(
            DB::table('stock_movement')
                ->where('product_variant_id', 1)
                ->where('type', StockMovementType::Sale->value)
                ->where('quantity_change', -2)
                ->exists(),
            'Phần trong tồn phải ghi movement loại sale (-2).',
        );
    }
}
