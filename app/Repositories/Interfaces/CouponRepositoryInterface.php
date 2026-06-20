<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Coupon;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface CouponRepositoryInterface extends BaseRepositoryInterface
{
    public function resolveCoupon(?string $code, array $cartItems, int $cartSubtotal): array;

    public function listActiveForUser(?int $userGroupId): Collection;

    public function listSavedByUser(int $userId): Collection;

    public function findByCode(string $code): ?Coupon;

    public function countUsedByUser(int $userId, int $couponId): int;

    public function countUsedByUserForCoupons(int $userId, array $couponIds): array;

    public function flushCache(): void;
}
