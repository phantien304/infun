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
        return $this->model->whereIn('id', $productIds)->get();
    }

    public function getProductFeature()
    {
        return $this->resetModel()
            ->where('badge', 'feature')
            ->dateAvailable()
            ->with($this->buildWithRelationProduct())
            ->orderBy('id', 'DESC')
            ->take(6)
            ->get();
    }

    public function getProductLatest(int $limit = 6)
    {
        return $this->rememberCache(
            $this->buildLatestCacheKey($limit),
            fn() => $this->resetModel()
                ->dateAvailable()
                ->with($this->buildWithRelationProduct())
                ->orderBy('id', 'DESC')
                ->take($limit)
                ->get(),
            getCoreConfig('time.cache')
        );
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
            'categories.category.description',
            'specials' => function ($q) {
                $q->dateStartToEnd()
                    ->where('user_group_id', getUserGroupId())
                    ->orderBy('priority');
            },
        ];
    }
}
