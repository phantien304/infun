<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ProductCategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function categoryIdsForProducts(array $productIds): array;

    public function productIdsInCategories(array $productIds, array $categoryIds): array;

    public function syncForProduct(int $productId, array $items): void;
}
