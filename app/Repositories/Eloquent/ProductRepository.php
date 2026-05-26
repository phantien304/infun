<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Product;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository extends QueryableRepository implements ProductRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Product::class;
    }

    protected function allowedFilters(): array
    {
        return ['id', 'name'];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'created_at'];
    }

    protected function beforeBuild(Builder $query): Builder
    {
        return $this->clientScope($query);
    }

    public function getProductSpecials(array $productIds)
    {
        return $this->resetModel()->whereIn('id', $productIds)->get();
    }

    public function getProductFeature(int $limit = 6)
    {
        return $this->clientQuery()
            ->where('badge', 'feature')
            ->take($limit)
            ->get();
    }

    public function getProductLatest(int $limit = 6)
    {
        return $this->rememberCacheTagged(
            [getCoreConfig('cache.product_root'), getCoreConfig('cache.product_latest')],
            $this->latestCacheKey($limit),
            fn () => $this->clientQuery()->take($limit)->get(),
            getCoreConfig('time.cache')
        );
    }

    protected function clientQuery(): Builder
    {
        return $this->clientScope($this->baseQuery())
            ->with($this->clientRelations())
            ->orderBy('id', 'DESC');
    }

    protected function clientScope(Builder $query): Builder
    {
        return $query->dateAvailable();
    }

    protected function clientRelations(): array
    {
        return [
            'description',
            'manufacturer',
            'stockStatus',
            'productCategories.category.description',
            'productSpecial',
        ];
    }

    protected function latestCacheKey(int $limit): string
    {
        return implode('_', [getCoreConfig('cache.product_latest'), getUserGroupId(), getUserType(), $limit]) . '_';
    }
}
