<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ProductCategory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;

class ProductCategoryRepository extends QueryableRepository implements ProductCategoryRepositoryInterface
{
    public function model(): string
    {
        return ProductCategory::class;
    }

    public function categoryIdsForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        return ProductCategory::query()
            ->whereIn('product_id', $productIds)
            ->pluck('category_id')
            ->unique()
            ->values()
            ->all();
    }

    public function productIdsInCategories(array $productIds, array $categoryIds): array
    {
        if (empty($productIds) || empty($categoryIds)) {
            return [];
        }
        return ProductCategory::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('category_id', $categoryIds)
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();
    }
}
