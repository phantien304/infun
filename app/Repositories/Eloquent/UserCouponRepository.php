<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserCoupon;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserCouponRepositoryInterface;

class UserCouponRepository extends QueryableRepository implements UserCouponRepositoryInterface
{
    public function model(): string
    {
        return UserCoupon::class;
    }

    public function saveForUser(int $userId, int $couponId): void
    {
        UserCoupon::query()->updateOrInsert(
            ['user_id' => $userId, 'coupon_id' => $couponId],
            ['saved_at' => now()],
        );
    }

    public function unsaveForUser(int $userId, int $couponId): bool
    {
        return UserCoupon::query()
            ->where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->delete() > 0;
    }
}
