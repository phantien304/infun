<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Coupon;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface CouponRepositoryInterface extends BaseRepositoryInterface
{
    public function resolveCoupon(?string $code, array $cartItems, int $cartSubtotal): array;

    public function listActiveForUser(?int $userGroupId): Collection;

    public function listSavedByUser(int $userId): Collection;

    public function findByCode(string $code): ?Coupon;

    public function existsById(int $couponId): bool;

    public function isActiveForUserGroup(int $couponId, ?int $userGroupId): bool;

    public function incrementUsedCount(int $couponId, int $by = 1): int;

    public function decrementUsedCount(int $couponId, int $by): void;

    public function flushCache(): void;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Coupon;

    public function saveFromCms(?Coupon $coupon, array $data): Coupon;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Coupon;
}
