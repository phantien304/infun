<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Product;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ProductRepositoryInterface;

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
    public function getProductSpecials(array $productIds)
    {
        return $this->resetModel()->whereIn('id', $productIds)->get();
    }

    public function getProductFeature(int $limit = 6)
    {
        return $this->buildClientProductQuery()
            ->where('badge', 'feature')
            ->take($limit)
            ->get();
    }

    public function getProductLatest(int $limit = 6)
    {
        return $this->rememberCacheTagged(
            [getCoreConfig('cache.product_root'), getCoreConfig('cache.product_latest')],
            $this->buildLatestCacheKey($limit),
            fn() => $this->buildClientProductQuery()->take($limit)->get(),
            getCoreConfig('time.cache')
        );
    }

    protected function buildClientProductQuery()
    {
        return $this->resetModel()
            ->dateAvailable()
            ->with($this->buildWithRelationProduct())
            ->orderBy('id', 'DESC');
    }

    protected function buildLatestCacheKey(int $limit): string
    {
        return implode('_', [
            getCoreConfig('cache.product_latest'),
            getUserGroupId(),
            getUserType(),
            $limit,
        ]) . '_';
    }

    protected function buildWithRelationProduct()
    {
        return [
            'description',
            'manufacturer',
            'stockStatus',
            'productCategories.category.description',
            'productSpecial',
        ];
    }
}
