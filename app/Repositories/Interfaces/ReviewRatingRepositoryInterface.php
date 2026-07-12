<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ReviewRatingRepositoryInterface extends BaseRepositoryInterface
{
    public function insert(array $rows): void;
}
