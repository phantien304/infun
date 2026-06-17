<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor `coupon` từ OpenCart legacy sang Shopee-style.
 *
 * Đổi semantic:
 *  - `type CHAR(1)` ('F','P') → `type TINYINT UNSIGNED` (1=percent, 2=fixed, 3=freeship).
 *    Voucher freeship trước đây dùng cờ `shipping=1` riêng → gộp thành type=3,
 *    cột `shipping` giữ làm legacy backward-compat (deprecated).
 *  - `total` (đơn tối thiểu) → giữ + thêm alias `min_subtotal` ngữ nghĩa rõ.
 *    Backfill `min_subtotal = total`. Code mới đọc `min_subtotal`.
 *
 * Bổ sung Shopee feature:
 *  - `description` TEXT hiển thị card voucher modal.
 *  - `discount_max` cap VND cho voucher percent ("giảm 10% tối đa 50k").
 *  - `apply_scope` 0/1/2 (all / coupon_product / coupon_category).
 *  - `user_group_id` voucher exclusive cho 1 nhóm khách.
 *  - `used_count` denormalize SUM(coupon_history) tránh COUNT mỗi quota check.
 *  - `is_active` admin toggle on/off không phải xoá soft delete.
 *  - `sort_order` thứ tự hiển thị modal.
 *  - `badge` tag marketing ("HOT", "MỚI", "VIP").
 *
 * UNIQUE code: chống duplicate khi admin nhập tay.
 * INDEX (is_active, date_start, date_end): cover listApplicable query.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bước 1 — thêm cột mới (nullable / default an toàn cho data hiện có).
        Schema::table('coupon', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->decimal('discount_max', 15, 2)->nullable()->after('discount');
            $table->decimal('min_subtotal', 15, 2)->nullable()->after('total');
            $table->tinyInteger('apply_scope')->unsigned()->default(0)->after('shipping');
            $table->integer('user_group_id')->unsigned()->nullable()->after('logged');
            $table->integer('used_count')->unsigned()->default(0)->after('uses_customer');
            $table->tinyInteger('is_active')->unsigned()->default(1)->after('used_count');
            $table->integer('sort_order')->unsigned()->default(0)->after('is_active');
            $table->string('badge', 32)->nullable()->after('sort_order');
        });

        // Bước 2 — backfill data legacy.
        // type CHAR(1) → numeric (P=1 percent, F=2 fixed).
        DB::table('coupon')->whereIn('type', ['P', 'p'])->update(['type' => '1']);
        DB::table('coupon')->whereIn('type', ['F', 'f'])->update(['type' => '2']);

        // min_subtotal copy từ total.
        DB::statement('UPDATE coupon SET min_subtotal = total WHERE total IS NOT NULL');

        // shipping=1 → type=3 (freeship), Shopee phân biệt tab voucher freeship riêng.
        DB::statement('UPDATE coupon SET type = 3 WHERE shipping = 1');

        // Bước 3 — ALTER type CHAR(1) → TINYINT UNSIGNED.
        // KHÔNG dùng doctrine/dbal (chưa require trong project) → raw SQL.
        DB::statement('ALTER TABLE coupon MODIFY COLUMN type TINYINT UNSIGNED NOT NULL DEFAULT 2');

        // Bước 4 — indexes + UNIQUE code.
        // Guard: code có thể NULL trong data legacy → set rỗng thành unique-safe trước.
        DB::statement("UPDATE coupon SET code = CONCAT('LEGACY_', id) WHERE code IS NULL OR code = ''");

        Schema::table('coupon', function (Blueprint $table) {
            $table->unique('code', 'uq_coupon_code');
            $table->index(['is_active', 'date_start', 'date_end'], 'idx_coupon_active_window');
        });
    }

    public function down(): void
    {
        Schema::table('coupon', function (Blueprint $table) {
            $table->dropUnique('uq_coupon_code');
            $table->dropIndex('idx_coupon_active_window');
            $table->dropColumn([
                'description', 'discount_max', 'min_subtotal', 'apply_scope',
                'user_group_id', 'used_count', 'is_active', 'sort_order', 'badge',
            ]);
        });

        // Restore type CHAR(1).
        DB::statement('ALTER TABLE coupon MODIFY COLUMN type CHAR(1) NULL');
        DB::statement("UPDATE coupon SET type = 'P' WHERE type = '1'");
        DB::statement("UPDATE coupon SET type = 'F' WHERE type IN ('2', '3')");
    }
};
