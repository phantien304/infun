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

    /**
     * Dựng phần request-context chung (ip/UA/referrer/session) từ request
     * hiện tại. Query param là input hostile: `?utm_source[]=x` trả array
     * (TypeError vào ?string), giá trị dài quá varchar(64) nổ QueryException
     * — cả hai đều làm 500 route redirect → guard is_string + truncate.
     */
    public static function fromRequest(int $affiliateId): self
    {
        $q = static function (string $key): ?string {
            $v = request()->query($key);

            return is_string($v) && $v !== '' ? mb_substr($v, 0, 64) : null;
        };

        return new self(
            affiliateId: $affiliateId,
            sessionId: (string) session()->getId(),
            ip: mb_substr((string) getIpVisitor(), 0, 45), // helper chung: CF / X-Forwarded-For aware
            userAgent: mb_substr((string) request()->server('HTTP_USER_AGENT', ''), 0, 255),
            landingUrl: mb_substr((string) request()->fullUrl(), 0, 512),
            referrer: mb_substr((string) request()->server('HTTP_REFERER', ''), 0, 512) ?: null,
            utmSource: $q('utm_source'),
            utmMedium: $q('utm_medium'),
            utmCampaign: $q('utm_campaign'),
        );
    }
}
