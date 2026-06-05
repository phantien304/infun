<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tối ưu C (2026-06-10): Add covering composite index trên `review` để sort
 * default `?sort=-review.helpful_count` không phải filesort.
 *
 * Vấn đề trước:
 *  - Sort default theo helpful_count DESC.
 *  - Index hiện có: (product_id, status, created_at), (product_id, rating).
 *  - ORDER BY helpful_count KHÔNG có trong index → MySQL filesort 100-500 row
 *    per product. Với 500k review × hot product có hàng nghìn review → chậm.
 *
 * Index mới:
 *  - (product_id, status, helpful_count, deleted_at):
 *      Eq → product_id, status / Range → helpful_count đã sort sẵn / deleted_at
 *      cho whereNull.
 *  - InnoDB B+tree đi thẳng theo helpful_count DESC, không filesort.
 *  - Cũng cover: WHERE product_id=X AND status=1 AND deleted_at IS NULL
 *    ORDER BY helpful_count DESC LIMIT 10 → Index Range Scan only.
 *
 * Sau migrate:
 *  - php artisan cache:clear
 *  - Run trong MySQL: ANALYZE TABLE review;
 *  - Verify: EXPLAIN ... ORDER BY helpful_count DESC LIMIT 10
 *    Extra phải có "Using where" hoặc "Using index condition", KHÔNG có "filesort".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('review')) {
            return;
        }

        Schema::table('review', function (Blueprint $t) {
            if (! self::hasIndex('review', 'idx_review_product_status_helpful')) {
                $t->index(
                    ['product_id', 'status', 'helpful_count', 'deleted_at'],
                    'idx_review_product_status_helpful',
                );
            }
        });

        // ANALYZE để MySQL refresh cardinality statistics → optimizer pick đúng
        // index mới ngay từ query đầu tiên.
        try {
            DB::statement('ANALYZE TABLE review');
        } catch (\Throwable $e) {
            // Không fatal — admin có thể chạy thủ công sau.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('review')) {
            return;
        }

        Schema::table('review', function (Blueprint $t) {
            try {
                $t->dropIndex('idx_review_product_status_helpful');
            } catch (\Throwable $e) {
            }
        });
    }

    private static function hasIndex(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        );
        return ! empty($rows);
    }
};
