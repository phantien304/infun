<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit gift đã claim trong order.
 *
 *  - order_id INT signed → khớp `orders.id` legacy OpenCart.
 *  - gift_id RESTRICT delete — gift không xoá được nếu còn order tham chiếu
 *    (audit trail). Admin phải soft delete gift (deleted_at) thay vì xoá cứng.
 *  - gift_item_id RESTRICT cùng lý do.
 *
 * Composite PK (order_id, gift_item_id) — 1 order × 1 gift_item = 1 dòng,
 * quantity là số lượng. KHÔNG cho duplicate cùng order × cùng item.
 *
 * KHÔNG có status (used/cancelled) vì gift KHÔNG có quota cá nhân (uses_per_customer)
 * như coupon. Cancel order → CASCADE delete row → quota global trả lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_gift')) {
            return;
        }

        Schema::create('order_gift', function (Blueprint $table) {
            $table->integer('order_id');                        // legacy INT signed
            $table->unsignedBigInteger('gift_id');
            $table->unsignedBigInteger('gift_item_id');
            $table->integer('quantity')->unsigned()->default(1);
            $table->timestamps();

            $table->primary(['order_id', 'gift_item_id'], 'pk_og');

            $table->foreign('order_id', 'fk_og_order')
                ->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('gift_id', 'fk_og_gift')
                ->references('id')->on('gift')->restrictOnDelete();
            $table->foreign('gift_item_id', 'fk_og_item')
                ->references('id')->on('gift_item')->restrictOnDelete();

            $table->index('gift_id', 'idx_og_gift');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_gift');
    }
};
