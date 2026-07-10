<?php

namespace App\Enums;

enum RewardStatus: int
{
    case Pending = 0;
    case Available = 1;
    case Revoked = 2;

    public function countsTowardBalance(): bool
    {
        return $this === self::Available;
    }
}
