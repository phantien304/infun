<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP / variant cụ thể là 1 quà trong gift campaign.
 *
 *  - product_id INT signed → khớp legacy `product.id` (OpenCart INT(11)).
 *  - product_variant_id BIGINT unsigned NULL → khớp cluster mới
 *    (xem `2026_05_30_100002_create_product_variant_table`); NULL khi gift là
 *    SP simple không cần chọn variant.
 *  - gift_id BIGINT unsigned → khớp `gift.id` (Laravel native bigIncrements).
 *  - quantity tặng kèm (vd "tặng kèm 2 cái").
 *
 * UNIQUE (gift_id, product_id, product_variant_id) chống duplicate khi admin
 * thêm cùng SP 2 lần vào 1 gift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gift_item')) {
            return;
        }

        Schema::create('gift_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('gift_id');
            $table->integer('product_id');                      // legacy INT signed
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->integer('quantity')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->default(0);

            $table->foreign('gift_id', 'fk_gi_gift')
                ->references('id')->on('gift')->cascadeOnDelete();
            $table->foreign('product_id', 'fk_gi_product')
                ->references('id')->on('product')->restrictOnDelete();
            $table->foreign('product_variant_id', 'fk_gi_variant')
                ->references('id')->on('product_variant')->restrictOnDelete();

            $table->unique(['gift_id', 'product_id', 'product_variant_id'], 'uq_gi');
            $table->index('product_id', 'idx_gi_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_item');
    }
};
