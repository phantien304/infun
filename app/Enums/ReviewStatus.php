<?php

namespace App\Enums;

enum ReviewStatus: int
{
    case Pending = 0;
    case Approved = 1;
    case Rejected = 2;
    case Hidden = 3;

    public static function fromInput(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_numeric($value) ? (self::tryFrom((int) $value) ?? self::Pending) : self::Pending;
    }
}
