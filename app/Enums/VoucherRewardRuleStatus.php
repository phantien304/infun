<?php

namespace App\Enums;

enum VoucherRewardRuleStatus: int
{
    case Active = 1;
    case Paused = 2;

    public static function fromInput(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_numeric($value) ? self::tryFrom((int) $value) : null;
    }
}
