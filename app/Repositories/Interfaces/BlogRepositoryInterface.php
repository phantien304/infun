<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface BlogRepositoryInterface extends BaseRepositoryInterface
{
    public function getBlogLatest(int $limit = 5);
}
