<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ZoneRepositoryInterface extends BaseRepositoryInterface
{
    public function nameById(int $id): string;
}
