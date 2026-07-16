<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném ra khi số dư điểm thưởng không đủ tại thời điểm CHỐT ĐƠN (locking read
 * trong transaction) — chặn double-spend khi 2 đơn song song cùng tiêu một
 * số dư. Throw = rollback cả đơn, cùng vai CouponExhaustedException.
 */
class RewardExhaustedException extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly int $requested,
        public readonly int $available,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf(
            'Reward balance exhausted for user %d: requested %d, available %d',
            $userId,
            $requested,
            $available,
        ));
    }
}
