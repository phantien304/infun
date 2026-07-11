<?php

namespace App\Console\Commands;

use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Prune ledger affiliate_click (Phase 6 — AFFILIATE-PLAN.md): click chỉ cần
 * cho attribution trong cookie window (30d) + đối soát ngắn hạn; conversion
 * đã snapshot đủ (order_total/commission/rate) nên click cũ xóa được.
 * Xóa theo chunk tránh khóa bảng lâu. Chạy hằng ngày — xem routes/console.php.
 */
class PruneAffiliateClicksCommand extends Command
{
    protected $signature = 'affiliate:prune-clicks
        {--days= : Giữ lại bao nhiêu ngày (mặc định core config affiliate.click_retention_days = 90)}
        {--chunk=5000 : Số row xóa mỗi lượt}';

    protected $description = 'Xóa affiliate_click cũ hơn N ngày (mặc định 90) theo chunk';

    public function handle(AffiliateClickRepositoryInterface $clickRepo): int
    {
        $days = (int) ($this->option('days') ?? getCoreConfig('affiliate.click_retention_days', 90));
        if ($days < (int) getConfigDb('config_affiliate_cookie_days', 30)) {
            $this->error(sprintf(
                'Từ chối: retention %d ngày < cookie window %s ngày — sẽ phá attribution đang chạy.',
                $days,
                getConfigDb('config_affiliate_cookie_days', 30),
            ));

            return self::FAILURE;
        }

        $deleted = $clickRepo->pruneOlderThan($days, (int) $this->option('chunk'));

        $this->info(sprintf('Pruned %d affiliate click(s) older than %d days.', $deleted, $days));

        return self::SUCCESS;
    }
}
