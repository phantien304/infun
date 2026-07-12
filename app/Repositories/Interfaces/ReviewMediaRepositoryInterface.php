<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ReviewMediaRepositoryInterface extends BaseRepositoryInterface
{
    public function insert(array $rows): void;
}
