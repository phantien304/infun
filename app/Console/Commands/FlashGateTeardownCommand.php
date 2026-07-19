<?php

namespace App\Console\Commands;

use App\Services\Stock\FlashGateService;
use Illuminate\Console\Command;

/**
 * DEL key gate → variant quay về đường DB thuần (fail-open by design).
 * Chạy sau khi kết thúc flash-sale, hoặc trước khi tắt FLASH_GATE_ENABLED.
 */
class FlashGateTeardownCommand extends Command
{
    protected $signature = 'flash-gate:teardown {variant?* : ID variant} {--all : Teardown mọi variant trong index}';

    protected $description = 'Gỡ gate (DEL key quota) cho variant — về đường reservation DB thuần';

    public function handle(FlashGateService $gate): int
    {
        $ids = $this->option('all')
            ? $gate->gatedVariantIds()
            : array_map('intval', (array) $this->argument('variant'));

        if ($ids === []) {
            $this->info($this->option('all') ? 'Index rỗng — không có gì để teardown.' : 'Truyền ID variant hoặc dùng --all.');

            return self::SUCCESS;
        }

        foreach ($ids as $variantId) {
            $gate->teardown($variantId);
            $this->info(sprintf('variant %d → gate OFF', $variantId));
        }

        return self::SUCCESS;
    }
}
