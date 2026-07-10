<?php

namespace App\Enums;

enum RewardTransactionType: int
{
    /** Điểm tích khi đặt hàng (points dương, status bắt đầu Pending). */
    case OnOrder = 12;

    /** Điểm tiêu ở checkout (points ÂM, Available ngay). */
    case Redeem = 13;

    /** Hoàn điểm đã tiêu khi đơn hủy (points dương, bù row Redeem). */
    case RedeemRefund = 14;
}
