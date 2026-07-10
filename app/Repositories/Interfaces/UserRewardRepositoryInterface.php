<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRewardRepositoryInterface extends BaseRepositoryInterface
{
    public function getTotalPoints(int $userId): int;

    public function recordOrderReward(int $userId, int $orderId, int $points): void;

    public function activateOrderReward(int $orderId): void;

    public function revokeOrderReward(int $orderId): void;

    public function recordRedeem(int $userId, int $orderId, int $points): void;

    public function refundRedeem(int $orderId): void;

    public function getHistoryForUser(int $userId, int $perPage = 20): LengthAwarePaginator;
}
