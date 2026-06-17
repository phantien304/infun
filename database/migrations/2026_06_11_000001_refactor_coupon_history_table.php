<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm `status` để track lifecycle coupon use:
 *   0 = applied    (đang trong cart, order chưa thanh toán)
 *   1 = used       (order đã paid → trừ quota)
 *   2 = cancelled  (order cancel → trả quota lại)
 *
 * Trước đây coupon_history chỉ ghi khi order tạo → không phân biệt "đã thực sự
 * trừ quota" vs "tạm tính". Khi cart abandoned, quota bị "rò" vì không có
 * cleanup. Status giải quyết: cron clean status=0 nếu > X giờ, hoặc app layer
 * recompute lúc checkout submit.
 *
 * Index hỗ trợ 2 hot query:
 *   - (user_id, coupon_id, status) → "user A đã dùng coupon B bao nhiêu lần"
 *     check uses_per_customer.
 *   - (coupon_id, status) → "coupon B đã dùng bao nhiêu tổng" check uses_total.
 *
 * Backfill: rows hiện tại = legacy data, coi như status=1 (used).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_history', function (Blueprint $table) {
            $table->tinyInteger('status')->unsigned()->default(0)->after('amount');
        });

        DB::table('coupon_history')->update(['status' => 1]);

        Schema::table('coupon_history', function (Blueprint $table) {
            $table->index(['user_id', 'coupon_id', 'status'], 'idx_ch_user_coupon_status');
            $table->index(['coupon_id', 'status'], 'idx_ch_coupon_status');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_history', function (Blueprint $table) {
            $table->dropIndex('idx_ch_user_coupon_status');
            $table->dropIndex('idx_ch_coupon_status');
            $table->dropColumn('status');
        });
    }
};
