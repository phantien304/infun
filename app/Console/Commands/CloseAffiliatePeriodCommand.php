<?php

namespace App\Console\Commands;

use App\Services\Affiliate\AffiliatePayoutService;
use Illuminate\Console\Command;

/**
 * Chốt kỳ chi trả hoa hồng affiliate (Phase 5 — AFFILIATE-PLAN.md).
 *
 * Cùng một service với nút "Chốt kỳ" trong CMS — CỐ Ý không viết lại logic ở
 * đây: hai đường ghi tiền song song là hai đường lệch nhau theo thời gian.
 *
 * KHÔNG đưa vào Schedule mặc định: chốt kỳ là quyết định tài chính, phải có
 * người bấm sau khi xem trước. Muốn tự động thì thêm vào routes/console.php,
 * nhưng nên chạy `--dry` trước vài kỳ để đối chiếu.
 */
class CloseAffiliatePeriodCommand extends Command
{
    protected $signature = 'affiliate:close-period
        {period? : Kỳ dạng YYYY-MM (mặc định tháng hiện tại)}
        {--dry : Chỉ xem trước, không ghi gì}';

    protected $description = 'Gom hoa hồng đã duyệt + qua hold_days thành kỳ chi trả affiliate';

    public function handle(AffiliatePayoutService $service): int
    {
        $period = $this->argument('period');

        if ($this->option('dry')) {
            $preview = $service->preview($period);

            $this->info(sprintf(
                'Kỳ %s — hold %d ngày (mốc %s), ngưỡng tối thiểu %s',
                $preview['period'],
                $preview['hold_days'],
                $preview['cutoff'],
                number_format($preview['min_payout']),
            ));

            $this->table(
                ['affiliate_id', 'code', 'tên', 'số đơn', 'tiền'],
                array_map(fn (array $r) => [
                    $r['affiliate_id'],
                    $r['affiliate_code'],
                    $r['affiliate_name'],
                    $r['conversions'],
                    number_format($r['amount']),
                ], $preview['eligible']),
            );

            $this->line(sprintf(
                'Đủ điều kiện: %d KOL / %s đ. Chưa đạt ngưỡng: %d KOL.',
                count($preview['eligible']),
                number_format($preview['total_amount']),
                count($preview['below_minimum']),
            ));

            return self::SUCCESS;
        }

        $result = $service->closePeriod($period);

        $this->info(sprintf(
            'Kỳ %s: tạo %d kỳ chi trả, bỏ qua %d.',
            $result['period'],
            count($result['created']),
            count($result['skipped']),
        ));

        foreach ($result['created'] as $row) {
            $this->line(sprintf(
                '  payout #%d — affiliate %d — %s đ',
                $row['payout_id'],
                $row['affiliate_id'],
                number_format($row['amount']),
            ));
        }

        foreach ($result['skipped'] as $row) {
            $this->line(sprintf('  bỏ qua affiliate %d (%s)', $row['affiliate_id'], $row['reason']));
        }

        return self::SUCCESS;
    }
}
