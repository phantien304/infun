<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface UserRewardRepositoryInterface extends BaseRepositoryInterface
{
    public function getTotalPoints(int $userId): int;

    /**
     * Ghi reward khi tạo order. Idempotent theo (order_id, transaction_type) —
     * gọi nhiều lần với cùng order chỉ ghi 1 row.
     */
    public function recordOrderReward(int $userId, int $orderId, int $points): void;
}
