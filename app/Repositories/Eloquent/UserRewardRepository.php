<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserReward;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;

class UserRewardRepository extends QueryableRepository implements UserRewardRepositoryInterface
{
    /**
     * Transaction type constant: reward sinh ra khi user đặt order. Lưu cùng
     * row reward để phân biệt với reward từ campaign / refund / manual.
     * Migrate từ CheckoutController::TRANSACTION_REWARD_ON_ORDER cũ.
     */
    public const TRANSACTION_ON_ORDER = 12;

    public function model(): string
    {
        return UserReward::class;
    }

    public function getTotalPoints(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int) $this->resetModel()
            ->where('user_id', $userId)
            ->sum('points');
    }

    public function recordOrderReward(int $userId, int $orderId, int $points): void
    {
        if ($userId <= 0 || $orderId <= 0) {
            return;
        }

        $exists = $this->resetModel()
            ->where('order_id', $orderId)
            ->where('transaction_type', self::TRANSACTION_ON_ORDER)
            ->exists();

        if ($exists) {
            return;
        }

        UserReward::create([
            'user_id'          => $userId,
            'order_id'         => $orderId,
            'order_status_id'  => getConfigDb('order_status_id'),
            'points'           => $points,
            'description'      => trans('messages.TextOrderId').' #'.$orderId,
            'transaction_type' => self::TRANSACTION_ON_ORDER,
        ]);
    }
}
