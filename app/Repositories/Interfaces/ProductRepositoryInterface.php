<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function getProductSpecials(array $productIds);
    public function getProductFeature();
    public function getProductLatest(int $limit = 6);
}
