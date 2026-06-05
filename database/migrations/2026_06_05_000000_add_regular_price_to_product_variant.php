<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm `product_variant.regular_price` — giá niêm yết (MSRP) per-variant.
 *
 * Mô hình giá Shopee-style:
 *  - regular_price: giá tham chiếu (struck-through), thường > price.
 *  - price: giá bán hiện tại (current). Có thể bằng regular_price (không sale)
 *    hoặc nhỏ hơn (đang sale).
 *  - discount % = (regular_price - price) / regular_price × 100, hiển thị
 *    khi price < regular_price.
 *
 * Nullable vì legacy variant chưa có giá niêm yết. JS fallback về
 * `product.price` khi `variant.regular_price` null — backward compat.
 *
 * Decimal(15,2) khớp với `product_variant.price` để tránh round drift khi
 * so sánh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->decimal('regular_price', 15, 2)->nullable()->after('price')
                ->comment('Giá niêm yết (MSRP) per-variant; struck-through ref. NULL = chưa set, fallback product.price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->dropColumn('regular_price');
        });
    }
};
