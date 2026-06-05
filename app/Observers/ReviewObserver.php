<?php

namespace App\Observers;

use App\Models\Entities\Review;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cập nhật aggregate cache trên `product` (review_count, rating_avg,
 * rating_sum, rating_distribution) khi review trạng thái APPROVED thay đổi.
 *
 * Quy tắc trigger:
 *  - created: nếu status = APPROVED ngay khi tạo (admin review trực tiếp)
 *    → cộng vào aggregate.
 *  - updated: nếu status đổi sang/khỏi APPROVED → cộng/trừ; nếu rating đổi
 *    trong khi vẫn APPROVED → diff sum + distribution.
 *  - deleted (soft + force): nếu đã APPROVED → trừ.
 *
 * Performance: dùng incremental UPDATE chứ KHÔNG re-aggregate full table.
 * Tag cache tự invalidate qua repo->forgetProductCache().
 */
class ReviewObserver
{
    public function created(Review $review): void
    {
        if ($review->status === getCoreConfig('review.status.approved')) {
            $this->applyDelta($review->product_id, $review->rating, +1);
            $this->invalidate($review->product_id);
        }
    }

    public function updated(Review $review): void
    {
        $wasApproved = (int) ($review->getOriginal('status')) === getCoreConfig('review.status.approved');
        $isApproved  = $review->status === getCoreConfig('review.status.approved');

        $oldRating = (int) $review->getOriginal('rating');
        $newRating = (int) $review->rating;

        if (! $wasApproved && $isApproved) {
            $this->applyDelta($review->product_id, $newRating, +1);
            $this->invalidate($review->product_id);
        } elseif ($wasApproved && ! $isApproved) {
            $this->applyDelta($review->product_id, $oldRating, -1);
            $this->invalidate($review->product_id);
        } elseif ($wasApproved && $isApproved && $oldRating !== $newRating) {
            // Đổi rating khi vẫn approved: trừ rating cũ, cộng rating mới.
            $this->applyDelta($review->product_id, $oldRating, -1);
            $this->applyDelta($review->product_id, $newRating, +1);
            $this->invalidate($review->product_id);
        }
    }

    public function deleted(Review $review): void
    {
        if ($review->status === getCoreConfig('review.status.approved')) {
            $this->applyDelta($review->product_id, $review->rating, -1);
            $this->invalidate($review->product_id);
        }
    }

    /**
     * Applay incremental delta lên aggregate. $sign = +1 hoặc -1.
     */
    protected function applyDelta(int $productId, int $rating, int $sign): void
    {
        $rating = max(1, min(5, $rating));
        $col = "s{$rating}";  // tạm dùng raw JSON_SET cho distribution

        DB::statement('
            UPDATE product
            SET review_count  = GREATEST(0, review_count + ?),
                rating_sum    = GREATEST(0, rating_sum + ? * ?),
                rating_avg    = CASE
                    WHEN GREATEST(0, review_count + ?) = 0 THEN 0
                    ELSE GREATEST(0, rating_sum + ? * ?) / GREATEST(1, review_count + ?)
                END,
                rating_distribution = COALESCE(
                    JSON_SET(
                        COALESCE(rating_distribution, JSON_OBJECT("1", 0, "2", 0, "3", 0, "4", 0, "5", 0)),
                        ?, GREATEST(0,
                            CAST(JSON_UNQUOTE(JSON_EXTRACT(
                                COALESCE(rating_distribution, JSON_OBJECT("1", 0, "2", 0, "3", 0, "4", 0, "5", 0)),
                                ?
                            )) AS UNSIGNED) + ?
                        )
                    ),
                    JSON_OBJECT("1", 0, "2", 0, "3", 0, "4", 0, "5", 0)
                ),
                rating_updated_at = NOW()
            WHERE id = ?
        ', [
            $sign,                // review_count
            $sign, $rating,       // rating_sum (sign * rating)
            $sign,                // review_count (CASE check)
            $sign, $rating,       // rating_sum (CASE)
            $sign,                // review_count (CASE)
            '$."' . $rating . '"', // JSON_SET path
            '$."' . $rating . '"', // JSON_EXTRACT path
            $sign,                // distribution delta
            $productId,
        ]);
    }

    protected function invalidate(int $productId): void
    {
        try {
            app(ReviewRepositoryInterface::class)->forgetProductCache($productId);
        } catch (\Throwable $e) {
            // Cache invalidate fail không được phá save flow.
            logError('ReviewObserver invalidate: ' . $e->getMessage());
        }
    }
}
