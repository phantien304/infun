<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface UserCouponRepositoryInterface extends BaseRepositoryInterface
{
    public function saveForUser(int $userId, int $couponId): void;

    public function unsaveForUser(int $userId, int $couponId): bool;
}
