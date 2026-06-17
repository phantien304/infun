<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopee "Mã của tôi" — user lưu voucher trước, dùng sau.
 *
 * Khi user click "Lưu" trên card voucher modal → insert row. Tab "Mã của tôi"
 * trong modal query bảng này JOIN coupon. User KHÔNG bắt buộc lưu trước mới
 * dùng được — có thể nhập code trực tiếp luôn. Lưu chỉ giúp user nhớ + UI
 * tiện chọn lại.
 *
 * Composite PK (user_id, coupon_id) cưỡng chế "1 user × 1 coupon = 1 row" ở
 * DB-level — chống duplicate khi double-click.
 *
 * FK CASCADE delete cả 2 bên: user xoá tài khoản hoặc coupon xoá → row tự
 * dọn. KHÔNG cần audit trail vì "save" là user preference, không phải giao
 * dịch.
 *
 * FK type:
 *  - `user_id`   → BIGINT UNSIGNED khớp `user.id` (Laravel `$table->id()`
 *    default — KHÔNG phải INT(11) như OpenCart convention vì bảng `user`
 *    là Laravel mới tạo, không phải import từ OpenCart dump).
 *  - `coupon_id` → INT(11) signed khớp `coupon.id` (OpenCart legacy).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_coupon')) {
            return;
        }

        Schema::create('user_coupon', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->integer('coupon_id');
            $table->timestamp('saved_at')->useCurrent();

            $table->primary(['user_id', 'coupon_id']);
            $table->index('coupon_id', 'idx_uc_coupon');

            $table->foreign('user_id', 'fk_uc_user')
                ->references('id')->on('user')->cascadeOnDelete();
            $table->foreign('coupon_id', 'fk_uc_coupon')
                ->references('id')->on('coupon')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_coupon');
    }
};
