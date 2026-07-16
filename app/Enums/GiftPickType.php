<?php

namespace App\Enums;

enum GiftPickType: int
{
    case Auto = 0;
    case PickOneOfN = 1;
    case PickUpToN = 2;

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
            self::Auto => trans('messages.checkout.gift.pick_auto'),
            self::PickOneOfN => trans('messages.checkout.gift.pick_one_of_n'),
            self::PickUpToN => trans('messages.checkout.gift.pick_up_to_n'),
        };
    }
}
