<?php

namespace App\Repositories\Interfaces;

use App\Enums\CouponHistoryStatus;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface CouponHistoryRepositoryInterface extends BaseRepositoryInterface
{
    public function countUsedByUser(int $userId, int $couponId): int;

    public function countUsedByUserForUpdate(int $userId, int $couponId): int;

    public function countUsedByUserForCoupons(int $userId, array $couponIds): array;

    public function recordApplied(int $couponId, ?int $userId, int $amount, CouponHistoryStatus $status): int;

    public function recordUsed(int $couponId, int $orderId, ?int $userId, int $amount, CouponHistoryStatus $status): void;

    public function forOrderByStatus(int $orderId, CouponHistoryStatus $status): Collection;

    public function markStatus(array $ids, CouponHistoryStatus $status): void;
}
