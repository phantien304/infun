<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregate cache trên `product` cho review.
 *
 * Lý do denormalize:
 *  - List trang product card 200 items → query AVG(rating) + COUNT(*) per
 *    product = correlated subquery N×M, không scale.
 *  - Sort theo "Rating cao nhất" cần `ORDER BY rating_avg DESC` index.
 *  - Distribution star (5★: 200 / 4★: 100 / ...) hiển thị badge trang chi tiết.
 *
 * Cập nhật qua observer trên ReviewObserver::saved/deleted:
 *  - INSERT review approved → rating_sum += rating, rating_count += 1
 *  - DELETE/REJECT → ngược lại
 *  - rating_avg = rating_sum / NULLIF(rating_count, 0)
 *  - rating_distribution: JSON {"1": n, "2": n, ...} update với incremental
 *    delta — KHÔNG re-aggregate full table.
 *
 * Cột:
 *   review_count       INT UNSIGNED DEFAULT 0    -- chỉ count review approved (status=1)
 *   rating_avg         DECIMAL(3,2) DEFAULT 0    -- 0.00 — 5.00
 *   rating_sum         INT UNSIGNED DEFAULT 0    -- helper cho incremental
 *   rating_distribution JSON DEFAULT NULL        -- {"1":5,"2":10,"3":50,"4":100,"5":200}
 *   rating_updated_at  TIMESTAMP NULL            -- last recompute
 *
 * Index:
 *   (rating_avg DESC, review_count DESC) — sort "đánh giá cao nhất, nhiều review"
 *
 * Backfill từ review.is_publish=1 legacy (status đã được sync ở migration
 * `000000`). NULL-safe để chạy được trên môi trường test chưa có review nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product')) {
            return;
        }

        Schema::table('product', function (Blueprint $t) {
            if (! Schema::hasColumn('product', 'review_count')) {
                $t->unsignedInteger('review_count')->default(0)->after('viewed');
            }
            if (! Schema::hasColumn('product', 'rating_avg')) {
                $t->decimal('rating_avg', 3, 2)->default(0)->after('review_count');
            }
            if (! Schema::hasColumn('product', 'rating_sum')) {
                $t->unsignedInteger('rating_sum')->default(0)->after('rating_avg');
            }
            if (! Schema::hasColumn('product', 'rating_distribution')) {
                $t->json('rating_distribution')->nullable()->after('rating_sum');
            }
            if (! Schema::hasColumn('product', 'rating_updated_at')) {
                $t->timestamp('rating_updated_at')->nullable()->after('rating_distribution');
            }
        });

        Schema::table('product', function (Blueprint $t) {
            if (! self::hasIndex('product', 'idx_product_rating_review')) {
                $t->index(['rating_avg', 'review_count'], 'idx_product_rating_review');
            }
        });

        // Backfill aggregate cho mọi product từ review approved hiện có.
        // SQL chạy 1 lần — tiếp đó observer giữ đồng bộ.
        DB::statement('
            UPDATE product p
            LEFT JOIN (
                SELECT product_id,
                       COUNT(*)               AS cnt,
                       SUM(rating)            AS rsum,
                       AVG(rating)            AS ravg,
                       SUM(rating = 1)        AS s1,
                       SUM(rating = 2)        AS s2,
                       SUM(rating = 3)        AS s3,
                       SUM(rating = 4)        AS s4,
                       SUM(rating = 5)        AS s5
                FROM review
                WHERE status = 1
                  AND deleted_at IS NULL
                GROUP BY product_id
            ) r ON r.product_id = p.id
            SET p.review_count         = COALESCE(r.cnt, 0),
                p.rating_sum           = COALESCE(r.rsum, 0),
                p.rating_avg           = COALESCE(r.ravg, 0),
                p.rating_distribution  = CASE
                    WHEN r.cnt IS NULL THEN NULL
                    ELSE JSON_OBJECT(
                        "1", COALESCE(r.s1, 0),
                        "2", COALESCE(r.s2, 0),
                        "3", COALESCE(r.s3, 0),
                        "4", COALESCE(r.s4, 0),
                        "5", COALESCE(r.s5, 0)
                    )
                END,
                p.rating_updated_at = NOW()
        ');
    }

    public function down(): void
    {
        if (! Schema::hasTable('product')) {
            return;
        }

        Schema::table('product', function (Blueprint $t) {
            try {
                $t->dropIndex('idx_product_rating_review');
            } catch (\Throwable $e) {
            }
            $t->dropColumn([
                'review_count', 'rating_avg', 'rating_sum',
                'rating_distribution', 'rating_updated_at',
            ]);
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
