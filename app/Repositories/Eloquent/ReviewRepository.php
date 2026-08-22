<?php

namespace App\Repositories\Eloquent;

use App\Enums\ReviewStatus;
use App\Models\Entities\Review;
use App\Models\Entities\ReviewCriteria;
use App\Models\Entities\ReviewReply;
use App\Models\Entities\ReviewReport;
use App\Models\Entities\ReviewTag;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ReviewRatingRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\ReviewTagRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ReviewRepository extends QueryableRepository implements ReviewRepositoryInterface
{
    use CacheableRepository;

    protected int $defaultPerPage = 10;
    protected int $maxPerPage = 50;

    public function model(): string
    {
        return Review::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('rating'),
            AllowedFilter::callback('has_media', function (Builder $q, $value) {
                if ($value) {
                    $q->where('media_count', '>', 0);
                }
            }),
            AllowedFilter::callback('has_text', function (Builder $q, $value) {
                if ($value) {
                    $q->whereNotNull('text')->where('text', '!=', '');
                }
            }),
            AllowedFilter::callback('tag', function (Builder $q, $value) {
                $codes = array_filter((array) $value);
                if (empty($codes)) {
                    return;
                }
                $q->whereHas('tags', fn ($qq) => $qq->whereIn('code', $codes));
            }),
        ];
    }

    protected function allowedSorts(): array
    {
        return [
            AllowedSort::field('review.helpful_count'),
            AllowedSort::field('review.created_at'),
            AllowedSort::field('review.rating'),
        ];
    }

    protected function defaultSort(): string
    {
        return '-review.helpful_count';
    }

    public function sortMenu(): array
    {
        return ['-review.helpful_count', '-review.created_at', 'review.created_at', '-review.rating', 'review.rating'];
    }

    public function perPageOptions(): array
    {
        return [10, 20, 50];
    }

    protected function withRelations(): array
    {
        $relations = [
            'reviewRatings.reviewCriteria.description',
            'reviewMedia',
            'reviewReplies.user',
            'reviewTags.description',
            'user',
            'productVariant.description',
        ];
        $userId = (int) getCurrentUserId();
        if ($userId > 0) {
            $relations['reviewHelpfuls'] = fn ($q) => $q->where('user_id', $userId);
        }
        return $relations;
    }

    protected function baseQuery(): Builder
    {
        return $this->resetModel()->query()->approved();
    }

    public function listForProduct(int $productId, ?Request $request = null): LengthAwarePaginator
    {
        return $this->list(
            $request,
            null,
            fn (Builder $q) => $q->forProduct($productId),
        );
    }

    public function getActiveCriteria(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.review.key_criteria_active'),
            fn () => ReviewCriteria::active()->with('description')->get(),
            now()->addDay(),
            tags: [getCoreConfig('cache.review.tag_criteria')],
        );
    }

    public function getActiveTags(int $limit = 8): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.review.key_tag_top').$limit,
            fn () => ReviewTag::active()->with('description')->limit($limit)->get(),
            now()->addHour(),
            tags: [getCoreConfig('cache.review.tag_tag')],
        );
    }

    public function getCriteriaAverages(int $productId): array
    {
        return $this->rememberCache(
            getCoreConfig('cache.review.key_criteria_avg').$productId,
            function () use ($productId) {
                $rows = DB::table('review_rating as rr')
                    ->join('review as r', 'r.id', '=', 'rr.review_id')
                    ->join('review_criteria as rc', 'rc.id', '=', 'rr.review_criteria_id')
                    ->where('r.product_id', $productId)
                    ->where('r.status', ReviewStatus::Approved->value)
                    ->whereNull('r.deleted_at')
                    ->select('rc.code', DB::raw('AVG(rr.rating) as avg_rating'))
                    ->groupBy('rc.code')
                    ->get();

                return $rows->mapWithKeys(fn ($r) => [$r->code => (float) $r->avg_rating])->all();
            },
            now()->addHour(),
            tags: [
                getCoreConfig('cache.review.tag_root'),
                getCoreConfig('cache.review.tag_product').$productId,
            ],
        );
    }

    public function findVerifiedOrderId(int $userId, int $productId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $completeStatus = (int) (getCoreConfig('config_complete_status') ?: 5);

        $row = DB::table('orders as o')
            ->join('orders_product as op', 'op.order_id', '=', 'o.id')
            ->where('o.user_id', $userId)
            ->where('op.product_id', $productId)
            ->where('o.order_status_id', $completeStatus)
            ->orderByDesc('o.id')
            ->select('o.id')
            ->first();

        return $row ? (int) $row->id : null;
    }

    public function hasReviewedFromOrder(int $userId, int $productId): bool
    {
        return $this->resetModel()->where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereNotNull('order_id')
            ->exists();
    }

    public function createReview(array $data): Review
    {
        return $this->resetModel()->create($data);
    }

    public function updateMediaCount(Review $review, int $count): void
    {
        $review->update(['media_count' => $count]);
    }

    public function lockReview(int $reviewId): Review
    {
        return $this->resetModel()->lockForUpdate()->findOrFail($reviewId);
    }

    public function saveReview(Review $review): void
    {
        $review->save();
    }

    public function forgetProductCache(int $productId): void
    {
        $this->forgetCacheTagged([getCoreConfig('cache.review.tag_product').$productId]);
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('cache.review.tag_root')]);
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['created_at', 'rating', 'status'], true)
            ? $request->input('sort')
            : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $status  = $request->input('status');
        $productId = $request->input('product_id');
        $rating  = $request->input('rating');
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()->with('product.description')->withCount('reviewReports');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        if ($productId) {
            $query->where('product_id', (int) $productId);
        }

        if ($rating) {
            $query->where('rating', (int) $rating);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('text', 'like', '%' . $keyword . '%')
                    ->orWhere('author', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Review
    {
        return $this->resetModel()->withTrashed()
            ->with([
                'product.description',
                'user',
                'reviewRatings.reviewCriteria.description',
                'reviewMedia' => fn ($q) => $q->reorder()->orderBy('sort_order'),
                'reviewReplies' => fn ($q) => $q->reorder()->orderBy('created_at'),
                'reviewReplies.user',
                'reviewTags.description',
                'reviewReports',
            ])
            ->find($id);
    }

    /**
     * `rating` tổng = trung bình `ratings` (rating theo từng tiêu chí) nếu
     * admin nhập breakdown theo ReviewCriteria — tính TRƯỚC khi create() để
     * App\Observers\ReviewObserver::created() cộng đúng ngay từ đầu (không
     * tính rồi sửa lại sau, vì created() chỉ fire 1 lần lúc insert).
     */
    public function createFromCms(array $data): Review
    {
        $status  = isset($data['status']) ? (int) $data['status'] : ReviewStatus::Approved->value;
        $ratings = $data['ratings'] ?? [];

        $rating = ! empty($ratings)
            ? max(1, min(5, (int) round(collect($ratings)->avg(fn ($r) => (int) $r['rating']))))
            : (int) $data['rating'];

        $review = $this->resetModel()->create([
            'product_id'    => (int) $data['product_id'],
            'author'        => $data['author'],
            'title'         => $data['title'] ?? null,
            'text'          => $data['text'],
            'rating'        => $rating,
            'status'        => $status,
            'is_publish'    => (bool) ($data['is_publish'] ?? true),
            'is_anonymous'  => (bool) ($data['is_anonymous'] ?? false),
            'language_code' => getConfigDb('config_language') ?: 'vi',
            'source'        => 'admin',
        ]);

        if (! empty($ratings)) {
            app(ReviewRatingRepositoryInterface::class)->upsertForReview($review->id, $ratings);
        }

        if (! empty($data['tag_ids'])) {
            $this->syncTags($review, array_map('intval', $data['tag_ids']));
        }

        if ($status === ReviewStatus::Approved->value) {
            $review->approved_at = now();
            $review->approved_by = auth()->id();
            $review->save();
        }

        return $review;
    }

    /**
     * Sửa toàn bộ nội dung review qua Eloquent save() bình thường (KHÔNG
     * update() raw) để App\Observers\ReviewObserver bắt được sự kiện
     * `updated` và cộng/trừ đúng product.review_count/rating_sum/rating_avg/
     * rating_distribution khi status hoặc rating đổi.
     *
     * `rating` tổng là trung bình của `ratings` (rating theo từng tiêu chí)
     * — giống hệt logic khách gửi review ở ReviewService::submitReview().
     * Nếu payload có `ratings` thì tự tính lại `rating` tổng (bỏ qua giá trị
     * `rating` gửi lên); chỉ dùng `rating` trực tiếp khi review không có
     * breakdown theo tiêu chí (review "mồi" admin tạo tay).
     */
    public function updateFromCms(Review $review, array $data): Review
    {
        $review->author       = $data['author'];
        $review->title        = $data['title'] ?? null;
        $review->text         = $data['text'];
        $review->is_publish   = (bool) ($data['is_publish'] ?? $review->is_publish);
        $review->is_anonymous = (bool) ($data['is_anonymous'] ?? $review->is_anonymous);

        if (! empty($data['ratings'])) {
            app(ReviewRatingRepositoryInterface::class)->upsertForReview($review->id, $data['ratings']);
            $avg = (float) DB::table('review_rating')->where('review_id', $review->id)->avg('rating');
            $review->rating = max(1, min(5, (int) round($avg)));
        } else {
            $review->rating = (int) $data['rating'];
        }

        if (array_key_exists('tag_ids', $data)) {
            $this->syncTags($review, array_map('intval', $data['tag_ids'] ?? []));
        }

        $status = (int) $data['status'];
        $review->status = $status;
        if ($status === ReviewStatus::Approved->value && ! $review->approved_at) {
            $review->approved_at = now();
            $review->approved_by = auth()->id();
        }
        $review->save();

        return $review;
    }

    protected function syncTags(Review $review, array $newIds): void
    {
        $tagRepo = app(ReviewTagRepositoryInterface::class);
        $oldIds  = DB::table('review_tag_pivot')->where('review_id', $review->id)->pluck('review_tag_id')
            ->map(fn ($v) => (int) $v)->all();

        $toAdd    = array_values(array_diff($newIds, $oldIds));
        $toRemove = array_values(array_diff($oldIds, $newIds));

        if (! empty($toAdd)) {
            $now = now();
            $tagRepo->insertPivots(array_map(fn ($id) => [
                'review_id'     => $review->id,
                'review_tag_id' => $id,
                'created_at'    => $now,
            ], $toAdd));
            $tagRepo->incrementUsage($toAdd);
        }

        if (! empty($toRemove)) {
            $tagRepo->removePivots($review->id, $toRemove);
            $tagRepo->decrementUsage($toRemove);
        }
    }

    public function addAdminReply(Review $review, string $text): ReviewReply
    {
        return DB::transaction(function () use ($review, $text) {
            $reply = ReviewReply::create([
                'review_id'   => $review->id,
                'user_id'     => auth()->id() ?? 0,
                'author_type' => ReviewReply::AUTHOR_ADMIN,
                'text'        => $text,
                'is_publish'  => true,
            ]);

            $review->increment('reply_count');

            return $reply->load('user');
        });
    }

    public function resolveReport(int $reportId, int $status, ?string $note = null): ?ReviewReport
    {
        $report = ReviewReport::find($reportId);
        if (! $report) {
            return null;
        }

        $report->status          = $status;
        $report->resolved_by     = auth()->id();
        $report->resolved_at     = now();
        $report->resolution_note = $note;
        $report->save();

        return $report;
    }

    /**
     * Xoá qua vòng lặp model — KHÔNG mass-delete bằng query builder
     * (Builder::delete()/restore() không fire event `deleted`/`restored`
     * theo từng dòng) để App\Observers\ReviewObserver bắt được, trừ đúng
     * rating trung bình sản phẩm cho các review đang Approved bị xoá.
     */
    public function deleteByIds(array $ids): int
    {
        $reviews = $this->resetModel()->whereIn('id', $ids)->get();
        foreach ($reviews as $review) {
            $review->delete();
        }

        return $reviews->count();
    }

    public function restoreByIds(array $ids): int
    {
        $reviews = $this->resetModel()->withTrashed()->whereIn('id', $ids)->get();
        foreach ($reviews as $review) {
            $review->restore();
        }

        return $reviews->count();
    }

    public function restoreById(int $id): ?Review
    {
        $review = $this->resetModel()->withTrashed()->find($id);
        $review?->restore();

        return $review;
    }
}
