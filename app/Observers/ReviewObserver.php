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

    /**
     * Đối xứng với deleted() — thiếu hook này thì khôi phục 1 review đã
     * Approved (tính năng mới ở CMS, trước đây Review không có đường xoá
     * nên restored() chưa từng cần) sẽ để rating trung bình sản phẩm bị
     * thiếu vĩnh viễn (đã trừ lúc xoá, không cộng lại lúc khôi phục).
     */
    public function restored(Review $review): void
    {
        if ($review->status === ReviewStatus::Approved->value) {
            $this->applyDelta($review->product_id, $review->rating, +1);
            $this->invalidate($review->product_id);
        }
    }

    /**
     * QUAN TRỌNG: MySQL đánh giá các mệnh đề SET trong 1 câu UPDATE theo thứ
     * tự trái→phải, mệnh đề sau đọc được giá trị CỘT ĐÃ ĐƯỢC CẬP NHẬT bởi
     * mệnh đề trước (không phải giá trị gốc trước UPDATE) — đã verify bằng
     * `UPDATE t SET a = a+1, b = a*2` cho b = (a+1)*2 chứ không phải a*2.
     * Vì vậy rating_avg KHÔNG được cộng lại `sign`/`rating` một lần nữa —
     * lúc mệnh đề rating_avg chạy thì review_count/rating_sum phía trên đã
     * là giá trị MỚI (đã cộng đúng 1 lần), cộng thêm lần nữa sẽ tính sai
     * gấp đôi (bug thật đã phát hiện lúc xoá review kiểm thử: review_count
     * 12→11, rating_sum 50→45 đúng, nhưng rating_avg ra 4.00 thay vì 4.09).
     */
    protected function applyDelta(int $productId, int $rating, int $sign): void
    {
        $rating = max(1, min(5, $rating));

        DB::statement('
            UPDATE product
            SET review_count  = GREATEST(0, review_count + ?),
                rating_sum    = GREATEST(0, rating_sum + ? * ?),
                rating_avg    = CASE
                    WHEN review_count = 0 THEN 0
                    ELSE rating_sum / review_count
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
