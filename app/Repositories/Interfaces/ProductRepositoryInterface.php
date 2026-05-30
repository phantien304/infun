<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Product;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function getByIds(array $productIds);

    public function getProductDetail(int $id): ?Product;

    public function incrementViewed(int $id): void;

    public function getProductRelatedByProductId(int $id, int $limit = 4);

    public function getProductFeature(int $limit = 6);

    public function getProductLatest(int $limit = 6);

    public function getProductRelated(array $productIds);

    public function getListSpecial(?Request $request = null): LengthAwarePaginator;

    public function getProductSpecialLatest(int $limit = 8);

    public function getSortMenu(): array;

    public function getPerPageMenu(): array;
}
