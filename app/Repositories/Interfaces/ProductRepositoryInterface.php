<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function getProductSpecials(array $productIds);
    public function getProductFeature(int $limit = 6);
    public function getProductLatest(int $limit = 6);
    public function getProductRelated(array $productIds);
    public function getListSpecial(?Request $request = null): LengthAwarePaginator;
    public function getProductSpecialLatest(int $limit = 8);
    public function getSortMenu(): array;
    public function getPerPageMenu(): array;
}
