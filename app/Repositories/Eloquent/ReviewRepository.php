<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Review;
use App\Models\Entities\ReviewCriteria;
use App\Models\Entities\ReviewTag;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
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
            $relations['helpfuls'] = fn ($q) => $q->where('user_id', $userId);
        }
        return $relations;
    }

    protected function baseQuery(): Builder
    {
        return Review::query()->approved();
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
                    ->where('r.status', getCoreConfig('review.status.approved'))
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

    /**
     * Verified purchase: user X đã hoàn thành order chứa product Y chưa.
     * Trả order_id (proof) để gắn vào review.order_id, hoặc null.
     * Status complete đọc từ config_complete_status (mặc định 5 — OpenCart).
     */
    public function findVerifiedOrderId(int $userId, int $productId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $completeStatus = (int) (getCoreConfig('config_complete_status') ?: 5);

        $row = DB::table('orders as o')
            ->join('orders_product as op', 'op.order_id', '=', 'o.id')
            ->where('o.customer_id', $userId)
            ->where('op.product_id', $productId)
            ->where('o.order_status_id', $completeStatus)
            ->orderByDesc('o.id')
            ->select('o.id')
            ->first();

        return $row ? (int) $row->id : null;
    }

    /**
     * Check user đã review product này từ order verified chưa — UNIQUE
     * (order_id, product_id) ở DB cũng enforce.
     */
    public function hasReviewedFromOrder(int $userId, int $productId): bool
    {
        return Review::where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereNotNull('order_id')
            ->exists();
    }

    public function forgetProductCache(int $productId): void
    {
        $this->forgetCacheTagged([getCoreConfig('cache.review.tag_product').$productId]);
    }

    /**
     * Flush toàn bộ cache review (tag root). Dùng từ observer khi admin
     * sửa criteria / tag list (vd thêm criteria mới → mọi product's averages
     * cần re-compute) hoặc khi bulk import review.
     */
    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('cache.review.tag_root')]);
    }
}
