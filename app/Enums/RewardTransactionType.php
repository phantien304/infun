<?php

namespace App\Enums;

enum RewardTransactionType: int
{
    case OnOrder = 12;

    case Redeem = 13;

    case RedeemRefund = 14;
}
