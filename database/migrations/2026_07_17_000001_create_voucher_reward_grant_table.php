<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biên bản PHÁT thưởng — "đơn X của user Y được rule Z tặng voucher V".
 *
 * Đây KHÔNG phải log sử dụng (sử dụng = voucher_history có sẵn). Vai trò:
 *
 *  1. Idempotency: UNIQUE (rule_id, order_id) → observer/job chạy trùng
 *     (retry, đơn bị lật status qua lại) thì insert lần 2 fail ở DB-level,
 *     KHÔNG phát đôi. Insert grant TRƯỚC, tạo voucher SAU, cùng transaction.
 *  2. Truy vết revoke: đơn bị hủy/refund sau khi đã tặng → tìm grant theo
 *     order_id → thu hồi voucher chưa dùng (voucher.status=4 Revoked).
 *  3. Đếm max_per_user: COUNT theo (rule_id, user_id) — idx_vrg_user cover.
 *
 * KHÔNG soft delete — biên bản phát là audit trail, chỉ insert.
 * KHÔNG FK constraint theo convention repo (orders/voucher legacy không FK);
 * toàn vẹn giữ bằng transaction + unique key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voucher_reward_grant')) {
            return;
        }

        Schema::create('voucher_reward_grant', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('rule_id')->unsigned();
            $table->integer('order_id')->unsigned();
            $table->integer('user_id')->unsigned()->nullable(); // đơn khách vãng lai: NULL, match theo email
            $table->string('email', 96);                        // denormalize từ orders.email — khách vãng lai vẫn đếm được max_per_user
            $table->integer('voucher_id')->unsigned();

            $table->timestamps();

            $table->unique(['rule_id', 'order_id'], 'uq_vrg_rule_order');
            $table->index(['rule_id', 'user_id'], 'idx_vrg_user');
            $table->index(['rule_id', 'email'], 'idx_vrg_email');
            $table->index('order_id', 'idx_vrg_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_reward_grant');
    }
};
