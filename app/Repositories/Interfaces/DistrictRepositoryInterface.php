<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface DistrictRepositoryInterface extends BaseRepositoryInterface
{
    public function listByZone(int $zoneId): Collection;

    /** Tên district theo id (kèm bản soft-deleted — snapshot đơn hàng). Không có → ''. */
    public function nameById(int $id): string;
}
