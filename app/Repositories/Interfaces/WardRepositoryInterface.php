<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface WardRepositoryInterface extends BaseRepositoryInterface
{
    public function listByDistrict(int $districtId): Collection;

    /** Tên ward theo id (kèm bản soft-deleted — snapshot đơn hàng). Không có → ''. */
    public function nameById(int $id): string;
}
