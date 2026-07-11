<?php

namespace App\Console\Commands;

use App\Enums\AffiliateConversionStatus;
use App\Models\Entities\Affiliate;
use App\Models\Entities\AffiliateClick;
use App\Models\Entities\AffiliateConversion;
use Illuminate\Console\Command;

/**
 * Cảnh báo affiliate bất thường (Phase 6 — AFFILIATE-PLAN.md), chạy hằng ngày:
 * - CR quá cao (conversion/click > max_cr_percent, mẫu ≥ min_clicks):
 *   nghi coupon abuse / self-referral vòng (mua hộ) — link ít click mà nhiều đơn.
 * - Click spam: click ≥ spam_clicks mà 0 conversion — nghi bơm click
 *   (bot/exchange traffic) chờ ăn may last-click.
 * Chỉ CẢNH BÁO (console + logError) — không tự khóa; admin xem xét rồi
 * suspend tay (Phase 5 TODO). Ngưỡng chỉnh ở core config affiliate.health.*.
 */
class AffiliateHealthCheckCommand extends Command
{
    protected $signature = 'affiliate:health-check
        {--days= : Cửa sổ thống kê (mặc định core config affiliate.health.window_days = 7)}';

    protected $description = 'Quét CR bất thường / click spam của các affiliate đang hoạt động';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? getCoreConfig('affiliate.health.window_days', 7));
        $minClicks = (int) getCoreConfig('affiliate.health.min_clicks', 50);
        $maxCr = (float) getCoreConfig('affiliate.health.max_cr_percent', 15);
        $spamClicks = (int) getCoreConfig('affiliate.health.spam_clicks', 500);
        $cutoff = now()->subDays(max(1, $days))->startOfDay();

        $clicks = AffiliateClick::where('created_at', '>=', $cutoff)
            ->groupBy('affiliate_id')
            ->selectRaw('affiliate_id, COUNT(*) as cnt')
            ->get()->pluck('cnt', 'affiliate_id');

        $conversions = AffiliateConversion::where('created_at', '>=', $cutoff)
            ->where('status', '!=', AffiliateConversionStatus::Rejected->value)
            ->groupBy('affiliate_id')
            ->selectRaw('affiliate_id, COUNT(*) as cnt')
            ->get()->pluck('cnt', 'affiliate_id');

        $affiliateIds = $clicks->keys()->merge($conversions->keys())->unique()->values();
        if ($affiliateIds->isEmpty()) {
            $this->info('Không có hoạt động affiliate trong cửa sổ quét.');

            return self::SUCCESS;
        }

        $codes = Affiliate::whereIn('id', $affiliateIds)->pluck('code', 'id');

        $warnings = [];
        foreach ($affiliateIds as $id) {
            $clickCount = (int) ($clicks[$id] ?? 0);
            $convCount = (int) ($conversions[$id] ?? 0);
            $cr = $clickCount > 0 ? round($convCount / $clickCount * 100, 2) : null;
            $code = (string) ($codes[$id] ?? "#{$id}");

            if ($clickCount >= $minClicks && $cr !== null && $cr > $maxCr) {
                $warnings[] = [$code, $clickCount, $convCount, $cr . '%', 'CR cao bất thường (nghi coupon/self-referral abuse)'];
            } elseif ($clickCount >= $spamClicks && $convCount === 0) {
                $warnings[] = [$code, $clickCount, $convCount, '0%', 'Click spam (nhiều click, 0 đơn)'];
            } elseif ($clickCount === 0 && $convCount >= 5) {
                // Coupon-only là hợp lệ (KOL đăng mã, khách không click) —
                // chỉ nhắc khi volume đáng kể để admin liếc qua nguồn mã.
                $warnings[] = [$code, $clickCount, $convCount, '—', 'Nhiều đơn toàn coupon, 0 click (liếc nguồn phát mã)'];
            }
        }

        if (empty($warnings)) {
            $this->info(sprintf('OK — %d affiliate hoạt động trong %d ngày, không có bất thường.', $affiliateIds->count(), $days));

            return self::SUCCESS;
        }

        $this->table(['Affiliate', 'Clicks', 'Conversions', 'CR', 'Cảnh báo'], $warnings);
        foreach ($warnings as $w) {
            logError(sprintf(
                'AffiliateHealthCheck [%s]: %s (clicks=%d, conversions=%d, cr=%s, window=%d ngày)',
                $w[0], $w[4], $w[1], $w[2], $w[3], $days,
            ));
        }

        return self::SUCCESS;
    }
}
