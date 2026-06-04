<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor `product_option_value` sau khi:
 *  - Bảng `product_option_value_2` đã bị drop (CLAUDE.md mục "Schema cluster variant").
 *  - Phát hiện `product_option` không có cột `id` — PK là composite (product_id, option_id).
 *    Cột `product_option_value.product_option_id` (số nguyên tham chiếu cột không tồn tại)
 *    là dangling FK legacy → DROP.
 *
 * Hướng B đã chọn: child tables dùng composite FK (product_id, option_id) tới
 * `product_option(product_id, option_id)`. Eloquent chuẩn không hỗ trợ —
 * package `awobaz/compoships` ở Base model bù phần này.
 *
 * Schema cuối của product_option_value:
 *   id               INT PK auto
 *   product_id       INT NOT NULL    ┐
 *   option_id        INT NOT NULL    ┘ composite FK → product_option(product_id, option_id) CASCADE
 *   option_value_id  INT NOT NULL    FK → option_value(id) RESTRICT
 *   image            VARCHAR
 *   sort_order       INT UNSIGNED    display order trong picker
 *   created_at, updated_at
 *
 *   UNIQUE (product_id, option_id, option_value_id) — 1 picker không có 2 entry
 *   cùng value (vd "Tone đỏ" chỉ xuất hiện 1 lần trong list "Chọn dây áo").
 *
 * Data `product_option_value` cũ KHÔNG cần giữ (user confirm) → TRUNCATE để
 * tránh FK constraint fail từ dangling `product_option_id` cũ. Caller phải
 * seed lại dữ liệu custom field picker sau migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_option_value')) {
            return;
        }

        // (1) TRUNCATE — data legacy không có cách map sang schema mới (cột
        //     product_option_id dangling, không tra ngược được option_id).
        //     User đã confirm data không cần giữ.
        DB::statement('SET foreign_key_checks=0');
        try {
            DB::table('product_option_value')->truncate();
        } finally {
            DB::statement('SET foreign_key_checks=1');
        }

        // (2) Drop cột legacy `product_option_id` (dangling FK).
        if (Schema::hasColumn('product_option_value', 'product_option_id')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                $t->dropColumn('product_option_id');
            });
        }

        // (3) Rename `option_value_1_id` → `option_value_id` nếu còn tên cũ.
        if (Schema::hasColumn('product_option_value', 'option_value_1_id')
            && ! Schema::hasColumn('product_option_value', 'option_value_id')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                $t->renameColumn('option_value_1_id', 'option_value_id');
            });
        }

        // (4) Add `option_id` (composite FK pair với product_id).
        if (! Schema::hasColumn('product_option_value', 'option_id')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                // INT signed — khớp option.id legacy (OpenCart convention).
                $t->integer('option_id')->after('product_id');
            });
        }

        // (5) Add `sort_order` cho UI picker.
        if (! Schema::hasColumn('product_option_value', 'sort_order')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                $t->unsignedInteger('sort_order')->default(0)->after('image');
            });
        }

        // (6) NOT NULL cho 3 FK column.
        DB::statement('ALTER TABLE product_option_value MODIFY product_id INT NOT NULL');
        DB::statement('ALTER TABLE product_option_value MODIFY option_id INT NOT NULL');
        DB::statement('ALTER TABLE product_option_value MODIFY option_value_id INT NOT NULL');

        // (7) Composite FK + scalar FK + UNIQUE. MySQL/MariaDB hỗ trợ composite
        //     FK reference đến composite PK miễn là 2 bảng cùng engine InnoDB
        //     (đã convert qua migration 2026_05_29_000000).
        Schema::table('product_option_value', function (Blueprint $t) {
            $t->foreign(['product_id', 'option_id'], 'fk_pov_product_option')
                ->references(['product_id', 'option_id'])->on('product_option')
                ->cascadeOnDelete();

            $t->foreign('option_value_id', 'fk_pov_option_value')
                ->references('id')->on('option_value')
                ->restrictOnDelete();

            $t->unique(
                ['product_id', 'option_id', 'option_value_id'],
                'uq_pov_value'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_option_value')) {
            return;
        }

        Schema::table('product_option_value', function (Blueprint $t) {
            $t->dropUnique('uq_pov_value');
            $t->dropForeign('fk_pov_product_option');
            $t->dropForeign('fk_pov_option_value');
        });

        // KHÔNG tự re-add cột product_option_id — không có data để backfill,
        // không phục hồi được semantic cũ. Down chỉ rollback constraints.
    }
};
