<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm 3 cột vào product:
 *  - has_variants: cờ short-circuit. Simple product (false) skip eager-load
 *    productOptions / variant tree. Trang list filter "product.has_variants=0"
 *    để render fast path.
 *  - min_variant_price / max_variant_price: denormalized aggregate. Tránh
 *    correlated subquery scalar mỗi request khi sort/filter theo giá.
 *    Cập nhật bằng observer ProductVariant::saved/deleted:
 *      $product->update([
 *        'min_variant_price' => $product->variants()->min('price'),
 *        'max_variant_price' => $product->variants()->max('price'),
 *      ]);
 *    Hoặc qua job dispatch nếu volume lớn.
 *
 * Composite index (has_variants, min_variant_price) tối ưu filter list page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false)->after('price');
            $table->decimal('min_variant_price', 15, 2)->nullable()->after('has_variants');
            $table->decimal('max_variant_price', 15, 2)->nullable()->after('min_variant_price');

            $table->index(['has_variants', 'min_variant_price'], 'idx_product_variant_price');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropIndex('idx_product_variant_price');
            $table->dropColumn(['has_variants', 'min_variant_price', 'max_variant_price']);
        });
    }
};
