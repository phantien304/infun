<?php

namespace App\Console\Commands;

use App\Repositories\Interfaces\ProductStockRepositoryInterface;
use App\Services\Stock\FlashGateService;
use App\Services\Stock\WarehouseService;
use Illuminate\Console\Command;

class FlashGateStatusCommand extends Command
{
    protected $signature = 'flash-gate:status {variant?* : ID variant (bỏ trống = mọi variant đang gated)}';

    protected $description = 'Xem quota gate còn lại vs sellable DB của các variant đang gated';

    public function handle(
        FlashGateService $gate,
        ProductStockRepositoryInterface $stockRepo,
        WarehouseService $warehouses,
    ): int {
        $ids = array_map('intval', (array) $this->argument('variant')) ?: $gate->gatedVariantIds();

        if ($ids === []) {
            $this->info('Không có variant nào đang gated. (enabled=' . var_export($gate->enabled(), true) . ')');

            return self::SUCCESS;
        }

        $warehouseIds = $warehouses->sellableWarehouseIds();
        $rows = [];
        foreach ($ids as $variantId) {
            $remaining = $gate->remaining($variantId);
            $dbSellable = (int) $stockRepo->sellableProductStocks($variantId, $warehouseIds)
                ->sum(fn ($s) => max(0, (int) $s->on_hand - (int) ($s->reserved ?? 0)));

            $rows[] = [
                $variantId,
                $remaining === null ? '(not gated)' : $remaining,
                $dbSellable,
                $remaining === null ? '—' : $remaining - $dbSellable,
            ];
        }

        // drift > 0 = gate hào phóng hơn DB (sẽ bị reconcile clamp);
        // drift < 0 = gate chặt hơn DB (hold sống chưa nhả / seed cũ) — vô hại.
        $this->table(['variant', 'gate còn', 'DB sellable', 'drift'], $rows);

        return self::SUCCESS;
    }
}
