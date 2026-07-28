<?php

namespace Tests\Feature;

use App\Models\Entities\Product;
use App\Services\ConfigDbService;
use App\Services\Product\ProductVariantWriter;
use App\Services\Stock\WarehouseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kiểm chứng ProductVariantWriter::sync() sau refactor giá/kho (2026-06/07):
 *  - Sản phẩm đơn giản: 1 default variant mang price/regular_price/minimum vào
 *    product_variant; on_hand/inventory_policy/subtract vào product_stock;
 *    special_price → product_variant_special.
 *  - Xoá special_price → soft-delete product_variant_special.
 *  - inventory_policy: untracked (2) → subtract=0; deny (0) → subtract=1.
 *  - Sản phẩm có biến thể: sinh đúng số variant + attribute + field per-SKU.
 *
 * Tự dựng schema tối thiểu trong setUp (không phụ thuộc full migration) — cùng
 * pattern với StockOversellTest, chạy được trên sqlite :memory: của phpunit.xml.
 * Observer aggregate có thể lỗi FLOOR() trên sqlite nhưng đã được bọc try/catch
 * nên không ảnh hưởng kết quả ghi.
 */
class ProductVariantWriteTest extends TestCase
{
    protected ProductVariantWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        // getConfigDb(...) không chạm bảng settings.
        $this->app->bind(ConfigDbService::class, function () {
            return new class () extends ConfigDbService {
                public function __construct()
                {
                }

                public function getConfigs(): array
                {
                    return [];
                }
            };
        });

        $this->createSchema();
        $this->seedProduct();

        // WarehouseService giả: defaultId = 1 (bỏ qua repo/cache warehouse).
        $warehouse = new class () extends WarehouseService {
            public function __construct()
            {
            }

            public function defaultId(): int
            {
                return 1;
            }
        };

        $this->writer = new ProductVariantWriter($warehouse);
    }

    private function createSchema(): void
    {
        Schema::create('product', function ($t) {
            $t->bigIncrements('id');
            $t->string('model')->nullable();
            $t->tinyInteger('has_variants')->default(0);
            $t->decimal('min_variant_price', 15, 2)->nullable();
            $t->decimal('max_variant_price', 15, 2)->nullable();
            $t->tinyInteger('max_variant_discount_percent')->unsigned()->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('product_option', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('option_id');
            $t->boolean('required')->default(0);
            $t->string('value')->nullable();
            $t->decimal('price', 15, 2)->default(0);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('option_value', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('option_id');
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('product_variant', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_id');
            $t->string('sku')->nullable();
            $t->string('attribute_signature')->nullable();
            $t->decimal('price', 15, 2)->default(0);
            $t->decimal('regular_price', 15, 2)->nullable();
            $t->integer('minimum')->default(1);
            $t->integer('points')->default(0);
            $t->decimal('weight', 15, 2)->nullable();
            $t->string('image')->nullable();
            $t->boolean('is_default')->default(0);
            $t->integer('sort_order')->default(0);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('product_variant_attribute', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_variant_id');
            $t->unsignedBigInteger('option_id');
            $t->unsignedBigInteger('option_value_id');
            $t->timestamps();
        });

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

        Schema::create('product_variant_special', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_variant_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedInteger('user_group_id')->default(1);
            $t->integer('priority')->default(0);
            $t->decimal('price', 15, 2);
            $t->dateTime('date_start')->nullable();
            $t->dateTime('date_end')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
    }

    private function seedProduct(): Product
    {
        DB::table('product')->insert([
            'id'         => 1,
            'model'      => 'SP-TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::find(1);
    }

    private function activeSpecials(int $variantId): int
    {
        return DB::table('product_variant_special')
            ->where('product_variant_id', $variantId)
            ->whereNull('deleted_at')
            ->count();
    }

    // ---------------------------------------------------------------------

    public function test_simple_product_writes_variant_stock_and_special(): void
    {
        $product = Product::find(1);

        $this->writer->sync($product, [], [[
            'id'                 => 0,
            'price'              => 150000,
            'regular_price'      => 200000,
            'sku'                => 'S1',
            'minimum'            => 3,
            'on_hand'            => 100,
            'inventory_policy'   => 2, // untracked
            'is_default'         => 1,
            'sort_order'         => 0,
            'special_price'      => 129000,
            'special_date_start' => '2026-07-19',
            'special_date_end'   => '2026-08-19',
            'option_value_ids'   => [],
        ]]);

        $v = DB::table('product_variant')->where('product_id', 1)->first();
        $this->assertNotNull($v);
        $this->assertEquals(150000, (float) $v->price);
        $this->assertEquals(200000, (float) $v->regular_price);
        $this->assertSame(3, (int) $v->minimum);
        $this->assertSame(1, (int) $v->is_default);

        $stock = DB::table('product_stock')->where('product_variant_id', $v->id)->first();
        $this->assertSame(100, (int) $stock->on_hand);
        $this->assertSame(2, (int) $stock->inventory_policy);
        $this->assertSame(0, (int) $stock->subtract); // untracked ⇒ không trừ kho

        $sp = DB::table('product_variant_special')
            ->where('product_variant_id', $v->id)->whereNull('deleted_at')->first();
        $this->assertNotNull($sp);
        $this->assertEquals(129000, (float) $sp->price);
        $this->assertSame(1, (int) $sp->user_group_id);
    }

    public function test_deny_policy_keeps_subtract_true(): void
    {
        $product = Product::find(1);

        $this->writer->sync($product, [], [[
            'id'               => 0,
            'price'            => 1000,
            'inventory_policy' => 0, // deny
            'on_hand'          => 5,
            'is_default'       => 1,
            'special_price'    => '',
            'option_value_ids' => [],
        ]]);

        $stock = DB::table('product_stock')->first();
        $this->assertSame(0, (int) $stock->inventory_policy);
        $this->assertSame(1, (int) $stock->subtract);
    }

    public function test_clearing_special_price_soft_deletes_special(): void
    {
        $product = Product::find(1);

        $this->writer->sync($product, [], [[
            'id'                 => 0,
            'price'              => 150000,
            'on_hand'            => 100,
            'inventory_policy'   => 0,
            'is_default'         => 1,
            'special_price'      => 129000,
            'special_date_start' => '2026-07-19',
            'special_date_end'   => '2026-08-19',
            'option_value_ids'   => [],
        ]]);

        $v = DB::table('product_variant')->where('product_id', 1)->first();
        $this->assertSame(1, $this->activeSpecials($v->id));

        // Lưu lại với special_price rỗng ⇒ special bị soft-delete.
        $this->writer->sync($product, [], [[
            'id'               => $v->id,
            'price'            => 150000,
            'on_hand'          => 100,
            'inventory_policy' => 0,
            'is_default'       => 1,
            'special_price'    => '',
            'option_value_ids' => [],
        ]]);

        $this->assertSame(0, $this->activeSpecials($v->id));
    }

    public function test_variant_matrix_writes_per_sku_fields(): void
    {
        $product = Product::find(1);

        DB::table('option_value')->insert([
            ['id' => 11, 'option_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'option_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->writer->sync(
            $product,
            [['option_id' => 1, 'role' => 1, 'required' => 0, 'value' => '', 'option_value_ids' => [11, 12]]],
            [
                [
                    'id' => 0, 'price' => 150000, 'regular_price' => 200000, 'sku' => 'V-S',
                    'minimum' => 1, 'on_hand' => 50, 'inventory_policy' => 0, 'is_default' => 1,
                    'sort_order' => 0, 'special_price' => '', 'option_value_ids' => [11],
                ],
                [
                    'id' => 0, 'price' => 160000, 'regular_price' => 210000, 'sku' => 'V-M',
                    'minimum' => 2, 'on_hand' => 30, 'inventory_policy' => 1, 'is_default' => 0,
                    'sort_order' => 1, 'special_price' => 139000,
                    'special_date_start' => '2026-07-19', 'special_date_end' => '2026-08-19',
                    'option_value_ids' => [12],
                ],
            ]
        );

        $this->assertSame(2, DB::table('product_variant')->where('product_id', 1)->whereNull('deleted_at')->count());
        $this->assertSame(2, DB::table('product_variant_attribute')->count());

        $vm = DB::table('product_variant')->where('sku', 'V-M')->first();
        $this->assertEquals(210000, (float) $vm->regular_price);
        $this->assertSame(2, (int) $vm->minimum);

        $stockM = DB::table('product_stock')->where('product_variant_id', $vm->id)->first();
        $this->assertSame(1, (int) $stockM->inventory_policy); // backorder

        $spM = DB::table('product_variant_special')
            ->where('product_variant_id', $vm->id)->whereNull('deleted_at')->first();
        $this->assertNotNull($spM);
        $this->assertEquals(139000, (float) $spM->price);
    }
}
