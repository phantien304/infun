<?php

namespace App\Enums;

enum CouponType: int
{
    case Percent = 1;  // discount = %
    case Fixed = 2;    // discount = VND
    case Freeship = 3; // ignore discount, shipping_fee = 0

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
            self::Percent => trans('messages.checkout.coupon.type_percent'),
            self::Fixed => trans('messages.checkout.coupon.type_fixed'),
            self::Freeship => trans('messages.checkout.coupon.type_freeship'),
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Percent => '%',
            self::Fixed => '₫',
            self::Freeship => '🚚',
        };
    }
}
