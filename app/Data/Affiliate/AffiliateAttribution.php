<?php

namespace App\Data\Affiliate;

use Spatie\LaravelData\Data;

/**
 * Kết quả attribution tại checkout — AffiliateAttributionService::resolve().
 * Đúng MỘT trong hai nguồn: couponCode (coupon riêng KOL, ưu tiên) hoặc
 * clickId (cookie click_token). affiliateUserId dùng chặn self-referral.
 * Phase 3: CreateOrderService dùng object này dựng AffiliateConversionData.
 */
class AffiliateAttribution extends Data
{
    public function __construct(
        public int $affiliateId,
        public int $affiliateUserId,
        public ?float $commissionRate = null, // rate riêng của KOL, null = theo rule/config
        public ?int $clickId = null,
        public ?string $couponCode = null,
    ) {
    }
}
