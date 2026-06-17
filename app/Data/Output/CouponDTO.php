<?php

namespace App\Data\Output;

use App\Models\Entities\Coupon;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Coupon DTO cho voucher card modal Shopee-style.
 *
 * Pre-compute label hiển thị + cờ trạng thái (savedByUser, applicableToCart,
 * notApplicableReason) trong DTO — blade chỉ render, không tính.
 *
 * `applicableToCart` + `notApplicableReason` cần cart context → set qua
 * factory `fromModelWithCart(Coupon, $userId, $isApplicable, $reason)`. Khi
 * chỉ liệt kê (vd "tất cả voucher khả dụng" KHÔNG check cart) → dùng
 * `fromModel()` thường, mặc định applicable=true.
 */
class CouponDTO extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,

        public int $type,                    // 1=percent, 2=fixed, 3=freeship
        public string $typeLabel,            // "Giảm theo %", "Giảm cố định", "Miễn phí ship"
        public string $typeIcon,             // "%", "₫", "🚚" — cho ribbon trái voucher card

        public float $discountValue,
        public ?float $discountMax,
        public string $discountLabel,        // "Giảm 10% tối đa 50,000đ" / "Giảm 30,000đ" / "Miễn phí vận chuyển"

        public ?float $minSubtotal,
        public string $minSubtotalLabel,     // "Đơn từ 200,000đ" hoặc "Không giới hạn"

        public int $applyScope,              // 0=all, 1=products, 2=categories
        public string $applyScopeLabel,      // "Tất cả SP", "Một số SP", "Một số ngành hàng"

        public ?string $badge,               // "HOT", "MỚI", ...
        public ?string $dateStart,           // ISO
        public ?string $dateEnd,             // ISO
        public string $expiresAtLabel,       // "HSD: 31/12/2026" hoặc "Còn 3 ngày"

        public ?int $usesTotal,
        public int $usedCount,
        public ?int $usesRemaining,          // NULL nếu unlimited
        public ?int $usesPerCustomer,

        public bool $savedByUser,
        public bool $applicableToCart,
        public ?string $notApplicableReason, // "Chưa đủ đơn tối thiểu", "Hết lượt", ...
    ) {}

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
        return match ($type) {
            (int) getCoreConfig('coupon.type.percent')  => 'Giảm theo %',
            (int) getCoreConfig('coupon.type.fixed')    => 'Giảm cố định',
            (int) getCoreConfig('coupon.type.freeship') => 'Miễn phí vận chuyển',
            default                                      => 'Voucher',
        };
    }

    private static function resolveTypeIcon(int $type): string
    {
        return match ($type) {
            (int) getCoreConfig('coupon.type.percent')  => '%',
            (int) getCoreConfig('coupon.type.fixed')    => '₫',
            (int) getCoreConfig('coupon.type.freeship') => '🚚',
            default                                      => '★',
        };
    }

    private static function resolveDiscountLabel(Coupon $coupon): string
    {
        $currency = (string) getConfigDb('config_currency');
        $type = (int) $coupon->type;

        if ($type === (int) getCoreConfig('coupon.type.percent')) {
            $label = 'Giảm ' . rtrim(rtrim(number_format((float) $coupon->discount, 2), '0'), '.') . '%';
            if ($coupon->discount_max !== null && (float) $coupon->discount_max > 0) {
                $label .= ' tối đa ' . number_format((float) $coupon->discount_max) . $currency;
            }
            return $label;
        }

        if ($type === (int) getCoreConfig('coupon.type.fixed')) {
            return 'Giảm ' . number_format((float) $coupon->discount) . $currency;
        }

        if ($type === (int) getCoreConfig('coupon.type.freeship')) {
            return 'Miễn phí vận chuyển';
        }

        return 'Voucher';
    }

    private static function resolveMinSubtotalLabel(Coupon $coupon): string
    {
        if ($coupon->min_subtotal === null || (float) $coupon->min_subtotal <= 0) {
            return 'Không giới hạn';
        }
        return 'Đơn từ ' . number_format((float) $coupon->min_subtotal) . (string) getConfigDb('config_currency');
    }

    private static function resolveApplyScopeLabel(int $scope): string
    {
        return match ($scope) {
            (int) getCoreConfig('coupon.apply_scope.all')        => 'Tất cả sản phẩm',
            (int) getCoreConfig('coupon.apply_scope.products')   => 'Một số sản phẩm',
            (int) getCoreConfig('coupon.apply_scope.categories') => 'Một số ngành hàng',
            default                                               => 'Tất cả sản phẩm',
        };
    }

    private static function resolveExpiresLabel(Coupon $coupon): string
    {
        if (! $coupon->date_end) {
            return 'Không giới hạn';
        }

        $end = Carbon::parse($coupon->date_end);
        $now = Carbon::now();

        if ($end->lt($now)) {
            return 'Đã hết hạn';
        }

        $diffDays = $now->diffInDays($end, false);
        if ($diffDays <= 7) {
            $diffHours = $now->diffInHours($end, false);
            if ($diffHours <= 24) {
                return 'Còn ' . max(1, (int) $diffHours) . ' giờ';
            }
            return 'Còn ' . (int) $diffDays . ' ngày';
        }

        return 'HSD: ' . $end->format('d/m/Y');
    }
}
