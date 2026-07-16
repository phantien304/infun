<?php

namespace App\Enums;

enum VoucherStatus: int
{
    case Active = 1;
    case Expired = 2;
    case FullyUsed = 3; // redeemed_balance = amount
    case Revoked = 4;   // admin thu hồi (fraud)

    public static function fromInput(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_numeric($value) ? self::tryFrom((int) $value) : null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => trans('messages.checkout.voucher.status_active'),
            self::Expired => trans('messages.checkout.voucher.status_expired'),
            self::FullyUsed => trans('messages.checkout.voucher.status_fully_used'),
            self::Revoked => trans('messages.checkout.voucher.status_revoked'),
        };
    }
}
