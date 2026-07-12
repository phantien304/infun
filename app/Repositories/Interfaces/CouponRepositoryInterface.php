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

    public function existsById(int $couponId): bool;

    public function isActiveForUserGroup(int $couponId, ?int $userGroupId): bool;

    public function saveForUser(int $userId, int $couponId): void;

    public function unsaveForUser(int $userId, int $couponId): bool;

    public function recordAppliedHistory(int $couponId, ?int $userId, int $amount, int $status): int;

    public function recordUsedHistory(int $couponId, int $orderId, ?int $userId, int $amount, int $status): void;

    public function incrementUsedCount(int $couponId, int $by = 1): void;

    public function decrementUsedCount(int $couponId, int $by): void;

    public function historyForOrderByStatus(int $orderId, int $status): Collection;

    public function markHistoryStatus(array $ids, int $status): void;

    public function categoryIdsForProducts(array $productIds): array;

    public function productIdsInCategories(array $productIds, array $categoryIds): array;

    public function flushCache(): void;
}
