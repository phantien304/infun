<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher = thẻ quà tặng cá nhân (gift card), KHÔNG phải coupon marketing.
 * User A mua voucher → tặng email cho B → B nhập code redeem → trừ thẳng VND
 * vào tổng đơn. Có thể dùng dần nhiều lần đến khi balance = 0.
 *
 * Schema legacy OpenCart đã có: id, order_id, code(10), from_*, to_*,
 * voucher_theme_id, message, amount, timestamps, deleted_at.
 *
 * Refactor thêm Shopee-style:
 *  - `code` VARCHAR(10) → VARCHAR(20). Code 10 ký tự brute-force quá rẻ.
 *  - `redeemed_balance` DECIMAL(15,2) denormalize SUM(voucher_history.amount)
 *    tránh COUNT mỗi redeem check (hot path).
 *  - `status` TINYINT: 1=active, 2=expired, 3=fully_used, 4=revoked.
 *    Trumps date check — admin có thể revoke voucher fraud bất kể date.
 *  - `date_expire` DATE — HSD (khác với deleted_at là soft delete).
 *  - `sent_at` TIMESTAMP — đánh dấu thời điểm email gửi to_email.
 *  - UNIQUE `code` chống collision.
 *  - INDEX `to_email` cover "voucher của tôi" query.
 *  - INDEX `(status, date_expire)` cover quota/expire check.
 *
 * KHÔNG hardcode literal — code đọc qua `getCoreConfig('voucher.status.*')`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bước 1 — thêm cột mới.
        Schema::table('voucher', function (Blueprint $table) {
            $table->tinyInteger('status')->unsigned()->default(1)->after('amount');
            $table->decimal('redeemed_balance', 15, 2)->default(0)->after('status');
            $table->date('date_expire')->nullable()->after('redeemed_balance');
            $table->timestamp('sent_at')->nullable()->after('date_expire');
        });

        // Bước 2 — backfill code VARCHAR(10) → VARCHAR(20) + xử lý null/duplicate
        // trước khi thêm UNIQUE.
        DB::statement("UPDATE voucher SET code = CONCAT('LEGACY_', id) WHERE code IS NULL OR code = ''");
        // Xử lý duplicate code: append _id để unique.
        DB::statement("
            UPDATE voucher v
            JOIN (SELECT code, MIN(id) AS first_id FROM voucher GROUP BY code HAVING COUNT(*) > 1) dup
              ON v.code = dup.code AND v.id != dup.first_id
            SET v.code = CONCAT(v.code, '_', v.id)
        ");

        DB::statement('ALTER TABLE voucher MODIFY COLUMN code VARCHAR(20) NOT NULL');

        // Bước 3 — indexes.
        Schema::table('voucher', function (Blueprint $table) {
            $table->unique('code', 'uq_voucher_code');
            $table->index('to_email', 'idx_voucher_to_email');
            $table->index(['status', 'date_expire'], 'idx_voucher_status_expire');
        });
    }

    public function down(): void
    {
        Schema::table('voucher', function (Blueprint $table) {
            $table->dropUnique('uq_voucher_code');
            $table->dropIndex('idx_voucher_to_email');
            $table->dropIndex('idx_voucher_status_expire');
            $table->dropColumn(['status', 'redeemed_balance', 'date_expire', 'sent_at']);
        });
        DB::statement('ALTER TABLE voucher MODIFY COLUMN code VARCHAR(10) NULL');
    }
};
