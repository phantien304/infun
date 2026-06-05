<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalized aggregate: MAX discount % across all variants của product.
 *
 * Dùng cho list page hiển thị badge "-X%" theo style Shopee — bait MAX
 * discount thay vì discount của default variant. List page CHỈ eager-load
 * cardRelations (không có productVariants), nên cần aggregate sẵn ở product
 * table thay vì compute on-the-fly (N+1).
 *
 * Cập nhật cùng cách với `min_variant_price`/`max_variant_price` — observer
 * trên ProductVariant::saved/deleted, hoặc backfill batch sau seed.
 *
 * Nullable: product không có variant → NULL → blade fallback về
 * special->discountPercent (logic cũ).
 *
 * TINYINT UNSIGNED đủ chứa 0–100, tiết kiệm space vs int.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->tinyInteger('max_variant_discount_percent')->unsigned()->nullable()
                ->after('max_variant_price')
                ->comment('MAX discount % across variants; cập nhật khi variant thay đổi');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn('max_variant_discount_percent');
        });
    }
};
