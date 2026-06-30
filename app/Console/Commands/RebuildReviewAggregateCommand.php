<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild aggregate review trên `product` (review_count, rating_avg,
 * rating_sum, rating_distribution).
 *
 * Tách khỏi reviews:seed để hỗ trợ workflow "split-run":
 *   1. `reviews:seed 200000 --no-aggregate` × 10 lần (mỗi lần process mới
 *      → PHP free memory, tránh leak tích luỹ).
 *   2. `reviews:rebuild-aggregate` 1 lần ở cuối.
 *
 * Cách dùng:
 *   php artisan reviews:rebuild-aggregate
 *   php artisan reviews:rebuild-aggregate --status=1  # custom status filter
 */
class RebuildReviewAggregateCommand extends Command
{
    protected $signature = 'reviews:rebuild-aggregate
        {--status= : review.status để filter (mặc định = review.status.approved trong config)}';

    protected $description = 'Rebuild product.review_count + rating_avg/sum/distribution từ review (group by SQL, 1 statement).';

    public function handle(): int
    {
        $status = $this->option('status');
        if ($status === null) {
            $status = (int) getCoreConfig('review.status.approved');
        }

        $started = microtime(true);
        $this->info("Rebuild aggregate cho mọi product (status={$status})…");

        // Trước khi rebuild: reset toàn bộ product về 0 → tránh dirty state
        // cho product không còn review nào (mà LEFT JOIN sẽ skip).
        DB::table('product')->update([
            'review_count'        => 0,
            'rating_sum'          => 0,
            'rating_avg'          => 0,
            'rating_distribution' => null,
            'rating_updated_at'   => now(),
        ]);

        DB::statement('
            UPDATE product p
            INNER JOIN (
                SELECT product_id,
                       COUNT(*)        AS cnt,
                       SUM(rating)     AS rsum,
                       AVG(rating)     AS ravg,
                       SUM(rating = 1) AS s1,
                       SUM(rating = 2) AS s2,
                       SUM(rating = 3) AS s3,
                       SUM(rating = 4) AS s4,
                       SUM(rating = 5) AS s5
                FROM review
                WHERE status = ?
                  AND deleted_at IS NULL
                GROUP BY product_id
            ) r ON r.product_id = p.id
            SET p.review_count        = r.cnt,
                p.rating_sum          = r.rsum,
                p.rating_avg          = r.ravg,
                p.rating_distribution = JSON_OBJECT(
                    "1", r.s1, "2", r.s2, "3", r.s3, "4", r.s4, "5", r.s5
                ),
                p.rating_updated_at   = NOW()
        ', [$status]);

        $touched = (int) DB::table('product')->where('review_count', '>', 0)->count();
        $elapsed = round(microtime(true) - $started, 2);
        $this->info("Xong: aggregate {$touched} product có review, mất {$elapsed}s.");

        return self::SUCCESS;
    }
}
