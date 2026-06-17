<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot — chỉ áp khi `gift.trigger_type = 2` (buy_specific_product).
 *
 * Khi user thêm bất kỳ SP trong list này vào cart → gift kích hoạt. Cart
 * service check qua whereIn(product_id, list).
 *
 * Composite PK chống duplicate. CASCADE cả 2 chiều.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gift_trigger_product')) {
            return;
        }

        Schema::create('gift_trigger_product', function (Blueprint $table) {
            $table->unsignedBigInteger('gift_id');
            $table->integer('product_id');                      // legacy INT signed

            $table->primary(['gift_id', 'product_id'], 'pk_gtp');

            $table->foreign('gift_id', 'fk_gtp_gift')
                ->references('id')->on('gift')->cascadeOnDelete();
            $table->foreign('product_id', 'fk_gtp_product')
                ->references('id')->on('product')->cascadeOnDelete();

            $table->index('product_id', 'idx_gtp_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_trigger_product');
    }
};
