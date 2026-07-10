<?php

namespace App\Data\Affiliate;

use Spatie\LaravelData\Data;

/**
 * Payload ghi 1 click vào ledger `affiliate_click`.
 * Dựng ở AffiliateRedirectController (qua short link, có linkId/subId/productId)
 * hoặc TrackAffiliateRef middleware (?ref= trực tiếp, các field link null).
 * click_token do repository tự sinh — không nằm trong DTO.
 */
class AffiliateClickData extends Data
{
    public function __construct(
        public int $affiliateId,
        public ?int $affiliateLinkId = null,
        public ?string $subId = null,
        public ?string $sessionId = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $landingUrl = null,
        public ?string $referrer = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?int $productId = null,
    ) {
    }

    /** Dựng phần request-context chung (ip/UA/referrer/session) từ request hiện tại. */
    public static function fromRequest(int $affiliateId): self
    {
        return new self(
            affiliateId: $affiliateId,
            sessionId: (string) session()->getId(),
            ip: (string) request()->server('REMOTE_ADDR', ''),
            userAgent: mb_substr((string) request()->server('HTTP_USER_AGENT', ''), 0, 255),
            landingUrl: mb_substr((string) request()->fullUrl(), 0, 512),
            referrer: mb_substr((string) request()->server('HTTP_REFERER', ''), 0, 512) ?: null,
            utmSource: request()->query('utm_source'),
            utmMedium: request()->query('utm_medium'),
            utmCampaign: request()->query('utm_campaign'),
        );
    }
}
