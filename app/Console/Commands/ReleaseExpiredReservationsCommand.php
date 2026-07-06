<?php

namespace App\Console\Commands;

use App\Services\Stock\StockService;
use Illuminate\Console\Command;

/**
 * Nhả các hold tồn kho đã hết hạn (stock_reservation.expires_at < now).
 * Chạy định kỳ (mỗi phút) — xem lịch trong routes/console.php.
 */
class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'stock:release-expired {--limit=500 : Số hold tối đa xử lý mỗi lần chạy}';

    protected $description = 'Nhả các hold tồn kho đã hết hạn về lại product_stock.reserved';

    public function handle(StockService $stock): int
    {
        $limit = (int) $this->option('limit');
        $released = $stock->releaseExpired($limit > 0 ? $limit : 500);

        $this->info(sprintf('Released %d expired stock reservation(s).', $released));

        return self::SUCCESS;
    }
}
