<?php

namespace App\Data\Affiliate;

use Spatie\LaravelData\Data;

/**
 * Payload ghi 1 conversion (hoa hồng) — Phase 3 sẽ được
 * AffiliateAttributionService + ConversionService dựng rồi đẩy vào
 * AffiliateConversionRepository::recordConversion().
 *
 * orderTotal / commission: BASE CURRENCY, snapshot tại thời điểm tạo đơn
 * (sau discount, TRƯỚC ship — business đã chốt). rate = % hiệu dụng (audit).
 * clickId / couponCode: nguồn attribution, một trong hai (coupon ưu tiên).
 */
class AffiliateConversionData extends Data
{
    public function __construct(
        public int $affiliateId,
        public int $orderId,
        public int $orderTotal,
        public int $commission,
        public float $rate,
        public ?int $clickId = null,
        public ?string $couponCode = null,
    ) {
    }

    public function valid(): bool
    {
        return $this->affiliateId > 0 && $this->orderId > 0 && $this->commission > 0;
    }
}
