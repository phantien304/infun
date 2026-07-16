<?php

namespace App\Enums;

enum CouponApplyScope: int
{
    case All = 0;
    case Products = 1;
    case Categories = 2;

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
            self::All => trans('messages.checkout.coupon.scope_all_label'),
            self::Products => trans('messages.checkout.coupon.scope_products_label'),
            self::Categories => trans('messages.checkout.coupon.scope_categories_label'),
        };
    }
}
