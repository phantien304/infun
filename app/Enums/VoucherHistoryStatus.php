<?php

namespace App\Enums;

enum VoucherHistoryStatus: int
{
    case Applied = 1;
    case Confirmed = 2;
    case Refunded = 3;

    public static function fromInput(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_numeric($value) ? self::tryFrom((int) $value) : null;
    }
}
