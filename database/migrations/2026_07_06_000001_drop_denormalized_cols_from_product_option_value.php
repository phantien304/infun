<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dọn nốt: bỏ 2 cột denormalized `product_id` / `option_id` khỏi
 * `product_option_value`.
 *
 * Sau migration 2026_07_06_000000 (thêm FK `product_option_id`), 2 cột này đã
 * THỪA — suy được 100% từ `product_option` qua `product_option_id`. Trước giữ
 * lại để giảm blast radius; giờ đã sửa hết caller nên dọn.
 *
 * UNIQUE đổi (product_id, option_id, option_value_id) → (product_option_id,
 * option_value_id) — tương đương ràng buộc "1 picker không có 2 entry cùng
 * value" vì (product_id, option_id) ↔ product_option_id là 1-1.
 *
 * Caller đã cập nhật:
 *  - ProductOptionService::buildCustomFieldOptions → lấy product_id/option_id từ
 *    parent ProductOption.
 *  - SeedProductVariantsCommand → insert không kèm 2 cột, xoá picker qua
 *    product_option_id.
 */
return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    public function up(): void
    {
        // Idempotent: nếu cột option_id đã bị bỏ → coi như đã chạy.
        if (! Schema::hasTable('product_option_value')
            || ! Schema::hasColumn('product_option_value', 'option_id')) {
            return;
        }

        // (1) Bỏ UNIQUE cũ + index sót của composite FF (fk_pov_product_option
        //     drop constraint ở migration trước nhưng index cùng tên còn lại).
        if ($this->indexExists('product_option_value', 'uq_pov_value')) {
            DB::statement('ALTER TABLE product_option_value DROP INDEX uq_pov_value');
        }
        if ($this->indexExists('product_option_value', 'fk_pov_product_option')) {
            DB::statement('ALTER TABLE product_option_value DROP INDEX fk_pov_product_option');
        }

        // (2) Drop 2 cột thừa.
        Schema::table('product_option_value', function (Blueprint $t) {
            $t->dropColumn(['product_id', 'option_id']);
        });

        // (3) UNIQUE mới trên khóa quan hệ thật.
        if (! $this->indexExists('product_option_value', 'uq_pov_product_option_value')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                $t->unique(['product_option_id', 'option_value_id'], 'uq_pov_product_option_value');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_option_value')
            || Schema::hasColumn('product_option_value', 'option_id')) {
            return;
        }

        // Re-add cột (nullable) → backfill từ product_option → set NOT NULL.
        Schema::table('product_option_value', function (Blueprint $t) {
            $t->integer('product_id')->nullable()->after('product_option_id');
            $t->integer('option_id')->nullable()->after('product_id');
        });

        DB::statement(
            'UPDATE product_option_value pov
                JOIN product_option po ON po.id = pov.product_option_id
                SET pov.product_id = po.product_id, pov.option_id = po.option_id'
        );

        DB::statement('ALTER TABLE product_option_value MODIFY product_id INT NOT NULL');
        DB::statement('ALTER TABLE product_option_value MODIFY option_id INT NOT NULL');

        if ($this->indexExists('product_option_value', 'uq_pov_product_option_value')) {
            DB::statement('ALTER TABLE product_option_value DROP INDEX uq_pov_product_option_value');
        }
        Schema::table('product_option_value', function (Blueprint $t) {
            $t->unique(['product_id', 'option_id', 'option_value_id'], 'uq_pov_value');
        });
    }
};
