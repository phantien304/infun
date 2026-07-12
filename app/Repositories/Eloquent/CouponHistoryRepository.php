<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\CouponHistory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\CouponHistoryRepositoryInterface;
use Illuminate\Support\Collection;

class CouponHistoryRepository extends QueryableRepository implements CouponHistoryRepositoryInterface
{
    public function model(): string
    {
        return CouponHistory::class;
    }

    public function countUsedByUser(int $userId, int $couponId): int
    {
        return $this->resetModel()->query()
            ->forUser($userId)
            ->forCoupon($couponId)
            ->usedOrApplied()
            ->count();
    }

    public function countUsedByUserForCoupons(int $userId, array $couponIds): array
    {
        if (empty($couponIds)) {
            return [];
        }

        return $this->resetModel()->query()
            ->forUser($userId)
            ->whereIn('coupon_id', $couponIds)
            ->usedOrApplied()
            ->selectRaw('coupon_id, COUNT(*) as aggregate')
            ->groupBy('coupon_id')
            ->pluck('aggregate', 'coupon_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function recordApplied(int $couponId, ?int $userId, int $amount, int $status): int
    {
        return (int) $this->resetModel()->insertGetId([
            'coupon_id'  => $couponId,
            'order_id'   => null,
            'user_id'    => $userId,
            'amount'     => $amount,
            'status'     => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function recordUsed(int $couponId, int $orderId, ?int $userId, int $amount, int $status): void
    {
        $this->resetModel()->create([
            'coupon_id' => $couponId,
            'order_id'  => $orderId,
            'user_id'   => $userId,
            'amount'    => $amount,
            'status'    => $status,
        ]);
    }

    public function forOrderByStatus(int $orderId, int $status): Collection
    {
        return $this->resetModel()->query()
            ->where('order_id', $orderId)
            ->where('status', $status)
            ->get(['id', 'coupon_id']);
    }

    public function markStatus(array $ids, int $status): void
    {
        if (empty($ids)) {
            return;
        }
        $this->resetModel()->query()
            ->whereIn('id', $ids)
            ->update(['status' => $status, 'updated_at' => now()]);
    }
}
