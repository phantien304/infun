<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm surrogate PK `id` cho `product_option` (trước đây PK kép
 * product_id+option_id, không có id — buộc phải dùng awobaz/compoships,
 * DELETE...JOIN, và child mang cả 2 cột khóa).
 *
 * Sau migration:
 *   product_option
 *     id                BIGINT UNSIGNED PK auto            ← MỚI
 *     product_id, option_id                                 giữ, thành UNIQUE(product_id, option_id)
 *     value, required, sort_order, timestamps               giữ nguyên
 *
 *   product_option_value
 *     id                INT PK auto                          giữ
 *     product_option_id BIGINT UNSIGNED NOT NULL             ← MỚI, FK → product_option(id) CASCADE
 *     product_id, option_id                                  GIỮ (nhiều chỗ đọc trực tiếp:
 *                                                            ProductOptionService, ProductResource, ProductData)
 *     option_value_id, image, sort_order, timestamps         giữ nguyên
 *
 * CHỦ ĐÍCH giữ product_id/option_id trên product_option_value: giảm blast
 * radius (code hiện đọc pov.product_id / pov.option_id trực tiếp). FK "thật"
 * chuyển sang product_option_id; composite FK cũ (fk_pov_product_option) bị
 * thay thế.
 *
 * Idempotent: kiểm tra hasColumn / tồn tại FK trước mỗi bước.
 *
 * XAMPP opcache CLI (xem CLAUDE.md): nếu migrate thấy chạy code cũ,
 *   php -d opcache.enable_cli=0 artisan migrate
 */
return new class extends Migration
{
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    public function up(): void
    {
        if (! Schema::hasTable('product_option') || ! Schema::hasTable('product_option_value')) {
            return;
        }

        // (1) Bỏ composite FK trên child để có thể tái cấu trúc PK của parent.
        if ($this->foreignKeyExists('product_option_value', 'fk_pov_product_option')) {
            DB::statement('ALTER TABLE product_option_value DROP FOREIGN KEY fk_pov_product_option');
        }

        // (2) product_option: drop PK kép, thêm surrogate id (PK), giữ khóa tự
        //     nhiên dưới dạng UNIQUE để (a) đảm bảo bất biến 1 (product, option),
        //     (b) làm điểm dedupe cho insertOrIgnore của seed.
        if (! Schema::hasColumn('product_option', 'id')) {
            DB::statement(
                'ALTER TABLE product_option
                    DROP PRIMARY KEY,
                    ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
                    ADD UNIQUE KEY uq_product_option (product_id, option_id)'
            );
        }

        // (3) product_option_value: thêm product_option_id, backfill, NOT NULL.
        if (! Schema::hasColumn('product_option_value', 'product_option_id')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                $t->unsignedBigInteger('product_option_id')->nullable()->after('id');
            });

            DB::statement(
                'UPDATE product_option_value pov
                    JOIN product_option po
                      ON po.product_id = pov.product_id
                     AND po.option_id  = pov.option_id
                    SET pov.product_option_id = po.id'
            );

            // Dọn orphan (nếu có picker không map được declaration nào) trước khi
            // set NOT NULL + FK, tránh migration fail giữa chừng.
            DB::table('product_option_value')->whereNull('product_option_id')->delete();

            DB::statement('ALTER TABLE product_option_value MODIFY product_option_id BIGINT UNSIGNED NOT NULL');
        }

        // (4) FK mới + index cho product_option_id.
        if (! $this->foreignKeyExists('product_option_value', 'fk_pov_product_option_id')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                $t->foreign('product_option_id', 'fk_pov_product_option_id')
                    ->references('id')->on('product_option')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_option') || ! Schema::hasTable('product_option_value')) {
            return;
        }

        // Gỡ FK + cột product_option_id trên child.
        if ($this->foreignKeyExists('product_option_value', 'fk_pov_product_option_id')) {
            DB::statement('ALTER TABLE product_option_value DROP FOREIGN KEY fk_pov_product_option_id');
        }
        if (Schema::hasColumn('product_option_value', 'product_option_id')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                $t->dropColumn('product_option_id');
            });
        }

        // Khôi phục PK kép trên parent.
        if (Schema::hasColumn('product_option', 'id')) {
            DB::statement(
                'ALTER TABLE product_option
                    DROP PRIMARY KEY,
                    DROP INDEX uq_product_option,
                    DROP COLUMN id,
                    ADD PRIMARY KEY (product_id, option_id)'
            );
        }

        // Khôi phục composite FK cũ.
        if (! $this->foreignKeyExists('product_option_value', 'fk_pov_product_option')) {
            DB::statement(
                'ALTER TABLE product_option_value
                    ADD CONSTRAINT fk_pov_product_option
                    FOREIGN KEY (product_id, option_id)
                    REFERENCES product_option (product_id, option_id)
                    ON DELETE CASCADE'
            );
        }
    }
};
