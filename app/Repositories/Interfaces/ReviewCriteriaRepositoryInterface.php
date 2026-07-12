<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface ReviewCriteriaRepositoryInterface extends BaseRepositoryInterface
{
    public function idsByCodes(array $codes): Collection;
}
