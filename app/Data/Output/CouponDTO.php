<?php

namespace App\Data\Output;

use App\Enums\CouponApplyScope;
use App\Enums\CouponType;
use App\Models\Entities\Coupon;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CouponDTO extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,
        public int $type,
        public string $typeLabel,
        public string $typeIcon,
        public float $discountValue,
        public ?float $discountMax,
        public string $discountLabel,
        public ?float $minSubtotal,
        public string $minSubtotalLabel,
        public int $applyScope,
        public string $applyScopeLabel,
        public ?string $badge,
        public ?string $dateStart,
        public ?string $dateEnd,
        public string $expiresAtLabel,
        public ?int $usesTotal,
        public int $usedCount,
        public ?int $usesRemaining,
        public ?int $usesPerCustomer,
        public bool $savedByUser,
        public bool $applicableToCart,
        public ?string $notApplicableReason,
    ) {
    }

    public static function fromModel(
        Coupon $coupon,
        bool $savedByUser = false,
        bool $applicableToCart = true,
        ?string $notApplicableReason = null,
    ): self {
        return new self(
            id:                  (int) $coupon->id,
            code:                (string) $coupon->code,
            name:                (string) $coupon->name,
            description:         $coupon->description,
            type:                (int) $coupon->type,
            typeLabel:           self::resolveTypeLabel((int) $coupon->type),
            typeIcon:            self::resolveTypeIcon((int) $coupon->type),
            discountValue:       (float) $coupon->discount,
            discountMax:         $coupon->discount_max !== null ? (float) $coupon->discount_max : null,
            discountLabel:       self::resolveDiscountLabel($coupon),
            minSubtotal:         $coupon->min_subtotal !== null ? (float) $coupon->min_subtotal : null,
            minSubtotalLabel:    self::resolveMinSubtotalLabel($coupon),
            applyScope:          (int) $coupon->apply_scope,
            applyScopeLabel:     self::resolveApplyScopeLabel((int) $coupon->apply_scope),
            badge:               $coupon->badge,
            dateStart:           $coupon->date_start?->format('Y-m-d H:i:s'),
            dateEnd:             $coupon->date_end?->format('Y-m-d H:i:s'),
            expiresAtLabel:      self::resolveExpiresLabel($coupon),
            usesTotal:           $coupon->uses_total !== null ? (int) $coupon->uses_total : null,
            usedCount:           (int) $coupon->used_count,
            usesRemaining:       $coupon->uses_total !== null
                ? max(0, (int) $coupon->uses_total - (int) $coupon->used_count)
                : null,
            usesPerCustomer:     $coupon->uses_customer !== null ? (int) $coupon->uses_customer : null,
            savedByUser:         $savedByUser,
            applicableToCart:    $applicableToCart,
            notApplicableReason: $notApplicableReason,
        );
    }

    private static function resolveTypeLabel(int $type): string
    {
        return CouponType::fromInput($type)?->label()
            ?? trans('messages.checkout.coupon.type_fallback');
    }

    private static function resolveTypeIcon(int $type): string
    {
        return CouponType::fromInput($type)?->symbol() ?? '★';
    }

    private static function resolveDiscountLabel(Coupon $coupon): string
    {
        $type = (int) $coupon->type;

        if ($type === CouponType::Percent->value) {
            $percent = rtrim(rtrim(number_format((float) $coupon->discount, 2), '0'), '.');

            if ($coupon->discount_max !== null && (float) $coupon->discount_max > 0) {
                return sprintf(
                    trans('messages.checkout.coupon.discount_percent_max'),
                    $percent,
                    money((float) $coupon->discount_max),
                );
            }

            return sprintf(trans('messages.checkout.coupon.discount_percent'), $percent);
        }

        if ($type === CouponType::Fixed->value) {
            return sprintf(trans('messages.checkout.coupon.discount_fixed'), money((float) $coupon->discount));
        }

        if ($type === CouponType::Freeship->value) {
            return trans('messages.checkout.coupon.type_freeship');
        }

        return trans('messages.checkout.coupon.type_fallback');
    }

    private static function resolveMinSubtotalLabel(Coupon $coupon): string
    {
        if ($coupon->min_subtotal === null || (float) $coupon->min_subtotal <= 0) {
            return trans('messages.checkout.coupon.min_subtotal_any');
        }
        return sprintf(trans('messages.checkout.coupon.min_subtotal_from'), money((float) $coupon->min_subtotal));
    }

    private static function resolveApplyScopeLabel(int $scope): string
    {
        return (CouponApplyScope::fromInput($scope) ?? CouponApplyScope::All)->label();
    }

    private static function resolveExpiresLabel(Coupon $coupon): string
    {
        if (! $coupon->date_end) {
            return trans('messages.checkout.coupon.expiry_none');
        }

        $end = Carbon::parse($coupon->date_end);
        $now = Carbon::now();

        if ($end->lt($now)) {
            return trans('messages.checkout.coupon.expiry_expired');
        }

        $diffDays = $now->diffInDays($end, false);
        if ($diffDays <= 7) {
            $diffHours = $now->diffInHours($end, false);
            if ($diffHours <= 24) {
                return sprintf(trans('messages.checkout.coupon.expiry_hours'), max(1, (int) $diffHours));
            }
            return sprintf(trans('messages.checkout.coupon.expiry_days'), (int) $diffDays);
        }

        return sprintf(trans('messages.checkout.coupon.expiry_date'), $end->format('d/m/Y'));
    }
}
