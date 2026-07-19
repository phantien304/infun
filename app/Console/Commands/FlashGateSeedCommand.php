<?php

namespace App\Console\Commands;

use App\Repositories\Interfaces\ProductStockRepositoryInterface;
use App\Services\Stock\FlashGateService;
use App\Services\Stock\WarehouseService;
use Illuminate\Console\Command;

/**
 * Chạy NGAY TRƯỚC giờ flash-sale cho từng variant tham gia sale.
 * Quota = Σ max(0, on_hand − reserved) các kho sellable, tính DƯỚI LOCK row
 * product_stock để không đua với checkout đang chạy (snapshot nhất quán).
 *
 * CMS sửa tồn GIỮA sale → phải chạy lại seed (reconcile chỉ tự clamp XUỐNG,
 * không tự nâng lên). Xem docs/FLASH-GATE.md Phase 2.
 */
class FlashGateSeedCommand extends Command
{
    protected $signature = 'flash-gate:seed {variant* : ID product_variant cần bật gate}';

    protected $description = 'Seed quota Redis gate = sellable hiện tại (bật admission cho variant flash-sale)';

    public function handle(
        FlashGateService $gate,
        ProductStockRepositoryInterface $stockRepo,
        WarehouseService $warehouses,
    ): int {
        if (! $gate->enabled()) {
            $this->error('FLASH_GATE_ENABLED đang tắt — bật env + config:cache trước rồi mới seed (không thì key seed xong không ai dùng).');

            return self::FAILURE;
        }

        $warehouseIds = $warehouses->sellableWarehouseIds();

        foreach ($this->argument('variant') as $variantId) {
            $variantId = (int) $variantId;
            if ($variantId <= 0) {
                continue;
            }

            $sellable = (int) $stockRepo->transaction(function () use ($stockRepo, $variantId, $warehouseIds) {
                return $stockRepo->lockSellableProductStocks($variantId, $warehouseIds)
                    ->sum(fn ($s) => max(0, (int) $s->on_hand - (int) ($s->reserved ?? 0)));
            });

            $gate->seed($variantId, $sellable);

            $this->info(sprintf('variant %d → gate %d suất (key %s)', $variantId, $sellable, $gate->key($variantId)));
        }

        return self::SUCCESS;
    }
}
