<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném ra khi số dư voucher không đủ tại thời điểm CHỐT ĐƠN — conditional
 * update (redeemed + amount <= amount tổng) trả affected = 0, tức 2 đơn song
 * song vừa tranh nhau một số dư và đơn này thua.
 *
 * Throw trong transaction tạo đơn = rollback cả đơn, cùng vai
 * CouponExhaustedException / RewardExhaustedException.
 */
class VoucherExhaustedException extends RuntimeException
{
    public function __construct(
        public readonly int $voucherId,
        public readonly string $voucherCode,
        public readonly float $requested,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf(
            'Voucher %s (#%d) exhausted: requested %.2f exceeds remaining balance',
            $voucherCode,
            $voucherId,
            $requested,
        ));
    }
}
