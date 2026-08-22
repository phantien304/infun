<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Review;
use App\Models\Entities\ReviewReply;
use App\Models\Entities\ReviewReport;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ReviewRepositoryInterface extends BaseRepositoryInterface
{
    public function listForProduct(int $productId, ?Request $request = null): LengthAwarePaginator;
    public function getActiveCriteria(): Collection;
    public function getActiveTags(int $limit = 8): Collection;
    public function getCriteriaAverages(int $productId): array;
    public function findVerifiedOrderId(int $userId, int $productId): ?int;
    public function hasReviewedFromOrder(int $userId, int $productId): bool;
    public function createReview(array $data): Review;
    public function updateMediaCount(Review $review, int $count): void;
    public function lockReview(int $reviewId): Review;
    public function saveReview(Review $review): void;
    public function forgetProductCache(int $productId): void;
    public function sortMenu(): array;
    public function perPageOptions(): array;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Review;

    /**
     * Admin tạo review "mồi" (seed review) — qua Review::create() bình
     * thường để ReviewObserver::created() cộng đúng rating trung bình
     * sản phẩm ngay nếu status tạo ra là Approved.
     */
    public function createFromCms(array $data): Review;

    /**
     * Sửa toàn bộ nội dung review (author/title/text/rating/status/is_publish/
     * is_anonymous) qua Eloquent save() bình thường (KHÔNG update() raw) để
     * App\Observers\ReviewObserver bắt được sự kiện `updated` và cộng/trừ
     * đúng product.review_count/rating_sum/rating_avg/rating_distribution
     * khi status hoặc rating đổi.
     */
    public function updateFromCms(Review $review, array $data): Review;

    public function addAdminReply(Review $review, string $text): ReviewReply;

    public function resolveReport(int $reportId, int $status, ?string $note = null): ?ReviewReport;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Review;
}
