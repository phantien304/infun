<?php

namespace App\Services\Affiliate;

use App\Enums\AffiliateConversionStatus;
use App\Models\Entities\Affiliate;
use App\Models\Entities\AffiliateLink;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;
use App\Repositories\Interfaces\AffiliateLinkRepositoryInterface;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Cổng affiliate cho KOL trong account section (Phase 4 — AFFILIATE-PLAN.md).
 * Controller giữ mỏng: mọi assemble số liệu dashboard + nghiệp vụ tạo link
 * (validate cùng domain, chặn loop /l/) nằm ở đây.
 */
class AffiliatePortalService
{
    public const CHART_DAYS = 30;

    public function __construct(
        protected AffiliateRepositoryInterface $affiliateRepo,
        protected AffiliateClickRepositoryInterface $clickRepo,
        protected AffiliateConversionRepositoryInterface $conversionRepo,
        protected AffiliateLinkRepositoryInterface $linkRepo,
    ) {
    }

    public function enabled(): bool
    {
        return (int) getConfigDb('config_affiliate_enabled') === 1;
    }

    public function findForUser(int $userId): ?Affiliate
    {
        return $this->affiliateRepo->findByUserId($userId);
    }

    /** Đăng ký KOL: pending hoặc active theo config_affiliate_auto_approve. */
    public function register(int $userId, array $paymentInfo): Affiliate
    {
        return $this->affiliateRepo->register($userId, $paymentInfo);
    }

    /**
     * Số liệu dashboard: stat cards theo status + series 30 ngày cho chart.
     * commission đơn vị đồng (int, base currency).
     */
    public function dashboard(Affiliate $affiliate): array
    {
        $totals = $this->conversionRepo->getStatusTotals((int) $affiliate->id);
        $get = fn (AffiliateConversionStatus $s, string $k) => $totals[$s->value][$k] ?? 0;

        return [
            'clicks_total'        => (int) $affiliate->clicks_count,
            'pending_count'       => $get(AffiliateConversionStatus::Pending, 'count'),
            'pending_commission'  => $get(AffiliateConversionStatus::Pending, 'commission'),
            'approved_count'      => $get(AffiliateConversionStatus::Approved, 'count'),
            'approved_commission' => $get(AffiliateConversionStatus::Approved, 'commission'),
            'paid_count'          => $get(AffiliateConversionStatus::Paid, 'count'),
            'paid_commission'     => $get(AffiliateConversionStatus::Paid, 'commission'),
            'rejected_count'      => $get(AffiliateConversionStatus::Rejected, 'count'),
            'chart'               => $this->chartSeries((int) $affiliate->id),
        ];
    }

    /** Labels đủ 30 ngày liên tục (ngày trống = 0) để chart không gãy trục. */
    protected function chartSeries(int $affiliateId): array
    {
        $clicks = $this->clickRepo->countByDay($affiliateId, self::CHART_DAYS);
        $conversions = $this->conversionRepo->countByDay($affiliateId, self::CHART_DAYS);

        $labels = $clickSeries = $convSeries = [];
        $day = Carbon::today()->subDays(self::CHART_DAYS - 1);
        for ($i = 0; $i < self::CHART_DAYS; $i++) {
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $clickSeries[] = $clicks[$key] ?? 0;
            $convSeries[] = $conversions[$key] ?? 0;
            $day = $day->addDay();
        }

        return ['labels' => $labels, 'clicks' => $clickSeries, 'conversions' => $convSeries];
    }

    /**
     * Tạo short link kiểu Shopee. Validate destination NGAY LÚC TẠO
     * (plan mục 2.4): chỉ nhận URL cùng domain / path tương đối, chặn chính
     * route /l/ (vòng lặp redirect). Trả [link, null] hoặc [null, lỗi].
     *
     * @return array{0: ?AffiliateLink, 1: ?string}
     */
    public function createLink(Affiliate $affiliate, string $url, ?string $subId = null): array
    {
        $maxLinks = (int) getCoreConfig('affiliate.max_links', 0);
        if ($maxLinks > 0 && $this->linkRepo->countForAffiliate((int) $affiliate->id) >= $maxLinks) {
            return [null, trans('messages.affiliate.link_limit', ['max' => $maxLinks])];
        }

        $destination = $this->normalizeDestination($url);
        if ($destination === null) {
            return [null, trans('messages.affiliate.link_invalid_domain')];
        }

        $link = $this->linkRepo->createLink(
            (int) $affiliate->id,
            $destination,
            $this->detectProductId($destination),
            filled($subId) ? mb_substr(trim($subId), 0, 64) : null,
        );

        return [$link, null];
    }

    /** Short URL đầy đủ để KOL copy: https://site.vn/l/{slug}. */
    public function shortUrl(AffiliateLink $link): string
    {
        return route('affiliate.redirect', ['slug' => $link->slug]);
    }

    /**
     * Chuẩn hóa destination về PATH tương đối (ổn định khi đổi domain/scheme):
     * - Nhận URL tuyệt đối cùng host app.url, hoặc path bắt đầu bằng '/'.
     * - Từ chối host lạ, path /l/ (loop), scheme lạ (javascript:...).
     */
    protected function normalizeDestination(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 512) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $path = $url;
        } else {
            $parts = parse_url($url);
            $host = $parts['host'] ?? null;
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($host === null || $appHost === null
                || strcasecmp($host, $appHost) !== 0
                || ! in_array($scheme, ['http', 'https'], true)) {
                return null;
            }
            $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        // Chặn trỏ vào chính shortener (loop) và các khu vực private.
        // So theo segment (trailing slash) để không dính oan /account-xyz.
        $bare = strtok($path, '?') ?: $path;
        foreach (['/l/', '/account/', '/checkout/', '/api/'] as $blocked) {
            if ($bare === rtrim($blocked, '/') || str_starts_with($bare, $blocked)) {
                return null;
            }
        }

        return mb_substr($path, 0, 512);
    }

    /**
     * Deep-link SP: URL sản phẩm dạng .../{slug}-p{id} (buildUrl convention)
     * → lưu product_id để report top SP theo KOL. Không match thì null.
     */
    protected function detectProductId(string $path): ?int
    {
        $bare = strtok($path, '?') ?: $path;
        if (preg_match('#-p(\d+)$#', $bare, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
