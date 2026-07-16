<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ZoneRepositoryInterface extends BaseRepositoryInterface
{
    /** Tên zone theo id (kèm cả bản đã soft-delete — dùng cho snapshot đơn hàng). Không có → ''. */
    public function nameById(int $id): string;
}
