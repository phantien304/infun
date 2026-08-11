<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * idx_product_variant_price (2026_05_30_100006) chỉ phủ min_variant_price
 * (sort giá tăng dần / filter). Sort giá GIẢM DẦN
 * (Product::scopeOrderByEffectivePriceFast('desc')) dùng max_variant_price —
 * không có index nào phủ cột này, filesort trên 500k dòng vẫn chậm dù đã bỏ
 * correlated subquery — sự cố production 2026-08-11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->index(['has_variants', 'max_variant_price'], 'idx_product_variant_price_max');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropIndex('idx_product_variant_price_max');
        });
    }
};
