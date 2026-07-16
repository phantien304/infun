<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface DistrictRepositoryInterface extends BaseRepositoryInterface
{
    public function listByZone(int $zoneId): Collection;

    public function nameById(int $id): string;
}
