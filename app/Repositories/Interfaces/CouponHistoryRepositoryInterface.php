<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface CouponHistoryRepositoryInterface extends BaseRepositoryInterface
{
    public function countUsedByUser(int $userId, int $couponId): int;

    public function countUsedByUserForCoupons(int $userId, array $couponIds): array;

    public function recordApplied(int $couponId, ?int $userId, int $amount, int $status): int;

    public function recordUsed(int $couponId, int $orderId, ?int $userId, int $amount, int $status): void;

    public function forOrderByStatus(int $orderId, int $status): Collection;

    public function markStatus(array $ids, int $status): void;
}
