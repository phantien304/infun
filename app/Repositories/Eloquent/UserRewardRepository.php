<?php

namespace App\Repositories\Eloquent;

use App\Enums\RewardStatus;
use App\Enums\RewardTransactionType;
use App\Models\Entities\UserReward;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;

/**
 * Ledger điểm thưởng (hybrid 2026-07-10). Semantics ở App\Enums\
 * RewardTransactionType (12=earn, 13=redeem âm, 14=refund) và RewardStatus
 * (0=pending, 1=available, 2=revoked).
 */
class UserRewardRepository extends QueryableRepository implements UserRewardRepositoryInterface
{
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
            ->where('status', RewardStatus::Available->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('points');
    }

    public function recordOrderReward(int $userId, int $orderId, int $points): void
    {
        if ($userId <= 0 || $orderId <= 0 || $points <= 0) {
            return;
        }

        $exists = $this->resetModel()
            ->where('order_id', $orderId)
            ->where('transaction_type', RewardTransactionType::OnOrder->value)
            ->exists();

        if ($exists) {
            return;
        }

        UserReward::create([
            'user_id'          => $userId,
            'order_id'         => $orderId,
            'order_status_id'  => getConfigDb('order_status_id'),
            'points'           => $points,
            'status'           => RewardStatus::Pending->value,
            'description'      => trans('messages.TextOrderId').' #'.$orderId,
            'transaction_type' => RewardTransactionType::OnOrder->value,
        ]);
    }

    public function activateOrderReward(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $months = (int) getConfigDb('config_reward_expiry_months');

        $this->resetModel()
            ->where('order_id', $orderId)
            ->where('transaction_type', RewardTransactionType::OnOrder->value)
            ->where('status', RewardStatus::Pending->value)
            ->update([
                'status'     => RewardStatus::Available->value,
                'expires_at' => $months > 0 ? now()->addMonths($months) : null,
            ]);
    }

    public function revokeOrderReward(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->resetModel()
            ->where('order_id', $orderId)
            ->where('transaction_type', RewardTransactionType::OnOrder->value)
            ->whereIn('status', [RewardStatus::Pending->value, RewardStatus::Available->value])
            ->update(['status' => RewardStatus::Revoked->value]);
    }

    public function recordRedeem(int $userId, int $orderId, int $points): void
    {
        if ($userId <= 0 || $orderId <= 0 || $points <= 0) {
            return;
        }

        $exists = $this->resetModel()
            ->where('order_id', $orderId)
            ->where('transaction_type', RewardTransactionType::Redeem->value)
            ->exists();

        if ($exists) {
            return;
        }

        UserReward::create([
            'user_id'          => $userId,
            'order_id'         => $orderId,
            'order_status_id'  => getConfigDb('order_status_id'),
            'points'           => -$points,
            'status'           => RewardStatus::Available->value,
            'description'      => trans('messages.TextOrderId').' #'.$orderId,
            'transaction_type' => RewardTransactionType::Redeem->value,
        ]);
    }

    public function refundRedeem(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $redeem = $this->resetModel()
            ->where('order_id', $orderId)
            ->where('transaction_type', RewardTransactionType::Redeem->value)
            ->first();

        if (! $redeem || (int) $redeem->points >= 0) {
            return;
        }

        $refunded = $this->resetModel()
            ->where('order_id', $orderId)
            ->where('transaction_type', RewardTransactionType::RedeemRefund->value)
            ->exists();

        if ($refunded) {
            return;
        }

        UserReward::create([
            'user_id'          => $redeem->user_id,
            'order_id'         => $orderId,
            'order_status_id'  => $redeem->order_status_id,
            'points'           => abs((int) $redeem->points),
            'status'           => RewardStatus::Available->value,
            'description'      => trans('messages.TextOrderId').' #'.$orderId,
            'transaction_type' => RewardTransactionType::RedeemRefund->value,
        ]);
    }
}
