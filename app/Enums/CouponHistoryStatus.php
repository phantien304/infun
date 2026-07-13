<?php

namespace App\Enums;

enum CouponHistoryStatus: int
{
    case Applied   = 0;
    case Used      = 1;
    case Cancelled = 2;

    public static function countedTowardUsage(): array
    {
        return [self::Applied, self::Used];
    }
}
