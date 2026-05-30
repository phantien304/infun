<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StoreReview;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;
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
        return $this->rememberCacheTagged(
            [getCoreConfig('cache.store_reviews')],
            $this->productCacheKey($productId, $limit),
            fn () => $this->resetModel()
                ->with($this->withRelations())
                ->where('product_id', $productId)
                ->orderBy('id', 'DESC')
                ->limit($limit)
                ->get()
        );
    }

    protected function featuredCacheKey(int $limit): string
    {
        return 'store_reviews_featured_' . $limit;
    }

    protected function productCacheKey(int $productId, int $limit): string
    {
        return implode('_', ['store_reviews_product', $productId, $limit]) . '_';
    }
}
