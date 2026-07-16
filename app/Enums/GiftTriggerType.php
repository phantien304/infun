<?php

namespace App\Enums;

enum GiftTriggerType: int
{
    case MinSubtotal = 1;
    case BuySpecificProduct = 2;

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
            self::MinSubtotal => trans('messages.checkout.gift.trigger_min_subtotal'),
            self::BuySpecificProduct => trans('messages.checkout.gift.trigger_buy_specific'),
        };
    }
}
