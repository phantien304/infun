<?php

namespace App\Observers;

use App\Enums\ReviewStatus;
use App\Models\Entities\Review;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReviewObserver
{
    public function created(Review $review): void
    {
        if ($review->status === ReviewStatus::Approved->value) {
            $this->applyDelta($review->product_id, $review->rating, +1);
            $this->invalidate($review->product_id);
        }
    }

    public function updated(Review $review): void
    {
        $wasApproved = (int) ($review->getOriginal('status')) === ReviewStatus::Approved->value;
        $isApproved  = $review->status === ReviewStatus::Approved->value;

        $oldRating = (int) $review->getOriginal('rating');
        $newRating = (int) $review->rating;

        if (! $wasApproved && $isApproved) {
            $this->applyDelta($review->product_id, $newRating, +1);
            $this->invalidate($review->product_id);
        } elseif ($wasApproved && ! $isApproved) {
            $this->applyDelta($review->product_id, $oldRating, -1);
            $this->invalidate($review->product_id);
        } elseif ($wasApproved && $isApproved && $oldRating !== $newRating) {
            $this->applyDelta($review->product_id, $oldRating, -1);
            $this->applyDelta($review->product_id, $newRating, +1);
            $this->invalidate($review->product_id);
        }
    }

    public function deleted(Review $review): void
    {
        if ($review->status === ReviewStatus::Approved->value) {
            $this->applyDelta($review->product_id, $review->rating, -1);
            $this->invalidate($review->product_id);
        }
    }

    protected function applyDelta(int $productId, int $rating, int $sign): void
    {
        $rating = max(1, min(5, $rating));
        $col = "s{$rating}";

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
            logError('ReviewObserver invalidate: ' . $e->getMessage());
        }
    }
}
