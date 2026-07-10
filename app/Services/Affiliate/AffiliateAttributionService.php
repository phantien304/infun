<?php

namespace App\Services\Affiliate;

use App\Data\Affiliate\AffiliateAttribution;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;

/**
 * Xác định đơn hiện tại thuộc về affiliate nào (AFFILIATE-PLAN.md mục 2.4).
 * Precedence (business đã chốt):
 *   1. Coupon riêng của KOL trong session.applied_coupons — khách từ
 *      story/TikTok gõ mã, không click link.
 *   2. Cookie aff_ref (click_token) — last-click, còn hạn cookie_days.
 * Self-referral: affiliate tự mua bằng link/mã của mình → null.
 * Phase 3 gọi service này trong CreateOrderService để dựng
 * AffiliateConversionData.
 */
class AffiliateAttributionService
{
    public function __construct(
        protected AffiliateRepositoryInterface $affiliateRepo,
        protected AffiliateClickRepositoryInterface $affiliateClickRepo,
    ) {
    }

    public function enabled(): bool
    {
        return (int) getConfigDb('config_affiliate_enabled') === 1;
    }

    public function resolve(): ?AffiliateAttribution
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->fromCoupon() ?? $this->fromCookie();
    }

    protected function fromCoupon(): ?AffiliateAttribution
    {
        $codes = (array) session()->get(getCoreConfig('session.applied_coupons'), []);
        foreach ($codes as $code) {
            $affiliate = $this->affiliateRepo->findActiveByCouponCode((string) $code);
            if (! $affiliate || $this->isSelfReferral((int) $affiliate->user_id)) {
                continue;
            }

            return new AffiliateAttribution(
                affiliateId: (int) $affiliate->id,
                affiliateUserId: (int) $affiliate->user_id,
                commissionRate: $affiliate->commission_rate !== null ? (float) $affiliate->commission_rate : null,
                couponCode: (string) $code,
            );
        }

        return null;
    }

    protected function fromCookie(): ?AffiliateAttribution
    {
        $token = (string) request()->cookie((string) getCoreConfig('affiliate.cookie'), '');
        if ($token === '') {
            return null;
        }

        $click = $this->affiliateClickRepo->findValidByToken(
            $token,
            (int) getConfigDb('config_affiliate_cookie_days', 30),
        );
        if (! $click || ! $click->affiliate || $this->isSelfReferral((int) $click->affiliate->user_id)) {
            return null;
        }

        return new AffiliateAttribution(
            affiliateId: (int) $click->affiliate_id,
            affiliateUserId: (int) $click->affiliate->user_id,
            commissionRate: $click->affiliate->commission_rate !== null
                ? (float) $click->affiliate->commission_rate
                : null,
            clickId: (int) $click->id,
        );
    }

    protected function isSelfReferral(int $affiliateUserId): bool
    {
        $currentUserId = (int) (getCurrentUserId() ?? 0);

        return $currentUserId > 0 && $currentUserId === $affiliateUserId;
    }
}
