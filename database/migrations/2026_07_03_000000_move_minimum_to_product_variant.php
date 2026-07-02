<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chuyển `minimum` (SL đặt tối thiểu) từ `product` → `product_variant`.
 *
 * Sau refactor 2026-05 đơn vị bán = variant (price/stock/sku/points/weight đều ở
 * product_variant). `minimum` là thuộc tính bán-hàng còn sót ở product → đưa về
 * đúng tầng SKU (giống Shopify/Magento: MOQ theo variant). `validateMinimum` đổi
 * sang kiểm THEO TỪNG DÒNG variant (per-SKU) thay vì gộp theo product.
 *
 * `$product->minimum` vẫn dùng được qua accessor getMinimumAttribute → defaultVariant
 * (giống accessor getPriceAttribute khi price được move trước đó).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_variant', 'minimum')) {
            Schema::table('product_variant', function (Blueprint $t) {
                $t->integer('minimum')->default(1)->after('points');
            });
        }

        // Backfill: mỗi variant nhận minimum của product cha (trước khi drop cột cũ).
        if (Schema::hasColumn('product', 'minimum')) {
            DB::statement(
                'UPDATE product_variant pv
                 JOIN product p ON p.id = pv.product_id
                 SET pv.minimum = COALESCE(p.minimum, 1)'
            );

            Schema::table('product', function (Blueprint $t) {
                $t->dropColumn('minimum');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product', 'minimum')) {
            Schema::table('product', function (Blueprint $t) {
                $t->integer('minimum')->default(1)->after('shipping');
            });

            // Best-effort: khôi phục minimum của product từ default variant.
            DB::statement(
                'UPDATE product p
                 JOIN product_variant pv ON pv.product_id = p.id AND pv.is_default = 1
                 SET p.minimum = pv.minimum'
            );
        }

        if (Schema::hasColumn('product_variant', 'minimum')) {
            Schema::table('product_variant', function (Blueprint $t) {
                $t->dropColumn('minimum');
            });
        }
    }
};
