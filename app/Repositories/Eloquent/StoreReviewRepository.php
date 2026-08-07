<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StoreReview;
use App\Models\Entities\StoreReviewDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedSort;

class StoreReviewRepository extends QueryableRepository implements StoreReviewRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return StoreReview::class;
    }

    protected function defaultSort(): string
    {
        return '-store_review.created_at';
    }

    protected function allowedSorts(): array
    {
        return [
            AllowedSort::field('created_at', 'store_review.created_at'),
            AllowedSort::field('name', 'store_review.name'),
        ];
    }

    protected function sortMenu(): array
    {
        return ['-created_at', 'created_at', 'name', '-name'];
    }

    protected function withRelations(): array
    {
        return [
            'description',
            'user',
        ];
    }

    public function getDetail(int $id)
    {
        return $this->resetModel()
            ->with($this->withRelations())
            ->find($id);
    }

    public function getStoreReviewsFeatured(int $limit = 20)
    {
        return $this->rememberCache(
            $this->featuredCacheKey($limit),
            fn () => $this->resetModel()
                ->with($this->withRelations())
                ->where('featured', 1)
                ->orderBy('id', 'DESC')
                ->limit($limit)
                ->get()
        );
    }

    public function getStoreReviewsByProduct(int $productId, int $limit = 16)
    {
        return $this->rememberCache(
            $this->productCacheKey($productId, $limit),
            fn () => $this->resetModel()
                ->with($this->withRelations())
                ->where('product_id', $productId)
                ->orderBy('id', 'DESC')
                ->limit($limit)
                ->get(),
            tags: [getCoreConfig('cache.store_reviews')],
        );
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('cache.store_reviews')]);
    }

    protected function featuredCacheKey(int $limit): string
    {
        return getCoreConfig('cache.store_reviews_featured') . $limit;
    }

    protected function productCacheKey(int $productId, int $limit): string
    {
        return implode('_', [getCoreConfig('cache.store_reviews_product'), $productId, $limit]) . '_';
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'store_review_description.title' : 'store_review.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $featured = $request->input('featured');
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = StoreReview::query()
            ->leftJoin('store_review_description', function ($join) use ($lang) {
                $join->on('store_review_description.store_review_id', '=', 'store_review.id')
                    ->where('store_review_description.language_code', '=', $lang);
            })
            ->select('store_review.*', 'store_review_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('store_review.name', 'like', '%' . $keyword . '%')
                    ->orWhere('store_review_description.title', 'like', '%' . $keyword . '%');
            });
        }

        if ($featured !== null && $featured !== '') {
            $query->where('store_review.featured', (int) $featured);
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?StoreReview
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?StoreReview $storeReview, array $data): StoreReview
    {
        return DB::transaction(function () use ($storeReview, $data) {
            $storeReview ??= new StoreReview();
            $storeReview->name        = $data['name'] ?? null;
            $storeReview->image       = $data['image'] ?? null;
            $storeReview->social_icon = $data['social_icon'] ?? 'instagram';
            $storeReview->featured    = (int) ($data['featured'] ?? 0);
            $storeReview->viewed      = (int) ($data['viewed'] ?? $storeReview->viewed ?? 0);
            $storeReview->product_id  = $data['product_id'] ?? null;
            $storeReview->author_id   = $data['author_id'] ?? null;
            $storeReview->save();

            foreach ((array) ($data['store_review_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = StoreReviewDescription::where('store_review_id', $storeReview->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new StoreReviewDescription();
                    $desc->store_review_id  = $storeReview->id;
                    $desc->language_code    = $code;
                    $desc->title            = $item['title'];
                    $desc->content          = $item['content'] ?? null;
                    $desc->meta_title       = $item['meta_title'] ?? null;
                    $desc->meta_description = $item['meta_description'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $storeReview->load('descriptions');
        });
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?StoreReview
    {
        $storeReview = $this->resetModel()->withTrashed()->find($id);
        $storeReview?->restore();

        return $storeReview?->load('descriptions');
    }
}
