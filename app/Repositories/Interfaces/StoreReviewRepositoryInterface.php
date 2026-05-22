<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface StoreReviewRepositoryInterface extends BaseRepositoryInterface
{
    public function getStoreReviewsFeatured(int $limit = 20);
}
