<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface WardRepositoryInterface extends BaseRepositoryInterface
{
    public function listByDistrict(int $districtId): Collection;
}
