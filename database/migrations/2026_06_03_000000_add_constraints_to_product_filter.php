<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product_filter: gắn PK + FK + trigger cho cluster filter (sau khi đã bổ
 * sung cột filter_id thủ công vào bảng legacy).
 *
 * Schema cuối:
 *   product_filter (
 *     product_id        INT,      FK → product.id        CASCADE
 *     filter_id         INT,      FK → filter.id         RESTRICT (denormalized)
 *     filter_value_id   INT,      FK → filter_value.id   RESTRICT
 *     PRIMARY KEY (product_id, filter_value_id),
 *     INDEX (filter_id, product_id)  -- reverse: products by filter group
 *   )
 *
 * Vì sao PK = (product_id, filter_value_id) chứ KHÔNG phải (product_id, filter_id):
 *  - Cho phép 1 product nhiều value trong cùng 1 filter group (vd áo M lẫn L).
 *  - Khớp với khai báo Eloquent ở `ProductFilter::$primaryKey` hiện tại,
 *    tránh drift app ↔ DB.
 *  - Nếu sau này có ràng buộc business "1 value/filter" thì validate ở app
 *    layer, không siết bằng PK schema.
 *
 * Vì sao filter_id vẫn cần:
 *  - Reverse query "các product nằm trong filter_id=X" KHÔNG cần JOIN sang
 *    filter_value. Index (filter_id, product_id) cover được query.
 *  - WHERE filter_id = ? trên 1 cột rẻ hơn JOIN + WHERE filter_value.filter_id.
 *
 * Drift hazard + trigger:
 *  - filter_id phải == filter_value.filter_id của row tương ứng. MySQL/MariaDB
 *    KHÔNG enforce được CHECK subquery, nên dùng TRIGGER BEFORE INSERT/UPDATE
 *    auto-set filter_id từ filter_value → app code chỉ cần insert
 *    (product_id, filter_value_id), filter_id tự derive, không thể sai.
 *  - Hệ quả: nếu app code cố ý set filter_id khác, trigger overwrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // (1) Đảm bảo cột filter_id tồn tại (idempotent — user đã add thủ công,
        //     guard để re-run an toàn).
        if (! Schema::hasColumn('product_filter', 'filter_id')) {
            Schema::table('product_filter', function (Blueprint $t) {
                $t->integer('filter_id')->nullable()->after('product_id');
            });
        }

        // (2) Backfill filter_id từ filter_value.filter_id. Chỉ update row
        //     còn NULL/0 — không đụng row đã có giá trị chủ động.
        DB::statement('
            UPDATE product_filter pf
            INNER JOIN filter_value fv ON fv.id = pf.filter_value_id
            SET pf.filter_id = fv.filter_id
            WHERE pf.filter_id IS NULL OR pf.filter_id = 0
        ');

        // (3) Xóa orphan rows TRƯỚC khi add FK (FK constraint fail nếu data
        //     vi phạm). 3 hướng:
        //       - product_id không có trong product
        //       - filter_value_id không có trong filter_value
        //       - filter_id NULL hoặc không có trong filter (sau backfill
        //         vẫn có thể NULL nếu filter_value của row đã bị xóa)
        DB::statement('
            DELETE pf FROM product_filter pf
            LEFT JOIN product p ON p.id = pf.product_id
            WHERE p.id IS NULL
        ');
        DB::statement('
            DELETE pf FROM product_filter pf
            LEFT JOIN filter_value fv ON fv.id = pf.filter_value_id
            WHERE fv.id IS NULL
        ');
        DB::statement('
            DELETE pf FROM product_filter pf
            LEFT JOIN filter f ON f.id = pf.filter_id
            WHERE f.id IS NULL OR pf.filter_id IS NULL
        ');

        // (4) Sửa filter_id sang NOT NULL với type khớp filter.id (INT signed
        //     OpenCart convention — xem CLAUDE.md mục "Convention DB").
        DB::statement('ALTER TABLE product_filter MODIFY filter_id INT NOT NULL');

        // (5) Drop PK cũ nếu có. Legacy có thể đang là PK
        //     (product_id, filter_value_id) 2-col, hoặc không có PK nào. Try/catch
        //     vì DROP PRIMARY KEY ném error 1091 nếu bảng không có PK.
        try {
            DB::statement('ALTER TABLE product_filter DROP PRIMARY KEY');
        } catch (\Throwable $e) {
            // không có PK cũ → bỏ qua, tiếp tục
        }

        // (6) PK + FK + index. Đặt tên explicit để down() drop chính xác.
        Schema::table('product_filter', function (Blueprint $t) {
            $t->primary(['product_id', 'filter_value_id'], 'pk_product_filter');

            $t->foreign('product_id', 'fk_product_filter_product')
                ->references('id')->on('product')
                ->cascadeOnDelete();

            $t->foreign('filter_id', 'fk_product_filter_filter')
                ->references('id')->on('filter')
                ->restrictOnDelete();

            $t->foreign('filter_value_id', 'fk_product_filter_value')
                ->references('id')->on('filter_value')
                ->restrictOnDelete();

            // Reverse lookup: "list product_id thuộc filter_id=X"
            $t->index(['filter_id', 'product_id'], 'idx_product_filter_by_filter');
        });

        // (7) Trigger derive filter_id từ filter_value.filter_id mỗi lần
        //     INSERT/UPDATE. Bảo vệ DB-level khỏi drift do app code.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_product_filter_bi');
        DB::unprepared('
            CREATE TRIGGER trg_product_filter_bi
            BEFORE INSERT ON product_filter
            FOR EACH ROW
            SET NEW.filter_id = (
                SELECT filter_id FROM filter_value WHERE id = NEW.filter_value_id
            )
        ');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_product_filter_bu');
        DB::unprepared('
            CREATE TRIGGER trg_product_filter_bu
            BEFORE UPDATE ON product_filter
            FOR EACH ROW
            SET NEW.filter_id = (
                SELECT filter_id FROM filter_value WHERE id = NEW.filter_value_id
            )
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_product_filter_bi');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_product_filter_bu');

        Schema::table('product_filter', function (Blueprint $t) {
            $t->dropForeign('fk_product_filter_product');
            $t->dropForeign('fk_product_filter_filter');
            $t->dropForeign('fk_product_filter_value');
            $t->dropIndex('idx_product_filter_by_filter');
            $t->dropPrimary('pk_product_filter');
        });

        // KHÔNG drop column filter_id — user thêm thủ công, không phải migration
        // này. KHÔNG khôi phục PK cũ vì không chắc legacy có PK nào.
    }
};
