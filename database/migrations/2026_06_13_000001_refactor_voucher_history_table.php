<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor voucher_history:
 *  - `status` TINYINT: 1=applied (đang ở cart, chưa thanh toán),
 *                     2=confirmed (order paid → balance trừ thật),
 *                     3=refunded (order cancel → balance trả lại).
 *  - `user_id` INT nullable — track user redeem (guest có thể dùng).
 *
 * Backfill row hiện tại: status=2 (confirmed) — coi như history cũ đã thanh
 * toán xong.
 *
 * Index hot query:
 *  - `(voucher_id, status)` — "balance đã dùng của voucher X" check.
 *  - `(user_id, status)` — "voucher user A đã dùng" (audit account page).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_history', function (Blueprint $table) {
            $table->tinyInteger('status')->unsigned()->default(1)->after('amount');
            $table->integer('user_id')->nullable()->after('order_id');
        });

        DB::table('voucher_history')->update(['status' => 2]);

        Schema::table('voucher_history', function (Blueprint $table) {
            $table->index(['voucher_id', 'status'], 'idx_vh_voucher_status');
            $table->index(['user_id', 'status'], 'idx_vh_user_status');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_history', function (Blueprint $table) {
            $table->dropIndex('idx_vh_voucher_status');
            $table->dropIndex('idx_vh_user_status');
            $table->dropColumn(['status', 'user_id']);
        });
    }
};
