<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Product;
use App\Models\Entities\ProductVariant;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface ProductCmsRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Product;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Product;

    public function saveProduct(Product $product): void;

    public function findWithDefaultVariant(int $id): ?Product;

    public function saveVariant(ProductVariant $variant): void;

    public function syncDescriptions(int $productId, array $items): void;

    public function syncFilters(int $productId, array $ids): void;

    public function syncRelated(int $productId, array $items): void;

    public function syncIngredients(int $productId, array $items): void;

    public function syncAttributes(int $productId, array $items): void;

    public function syncImages(int $productId, array $items): void;

    public function syncRewards(int $productId, array $items): void;
}
