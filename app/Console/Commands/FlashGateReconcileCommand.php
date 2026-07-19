<?php

namespace App\Console\Commands;

use App\Repositories\Interfaces\ProductStockRepositoryInterface;
use App\Services\Stock\FlashGateService;
use App\Services\Stock\WarehouseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cân lại quota gate ↔ DB, schedule everyMinute (routes/console.php).
 * CHỈ clamp XUỐNG: gate = min(gate, sellable_DB). Vì:
 *  - Chiều gate > DB (leak credit, Redis restart mất lệnh cuối, CMS giảm tồn)
 *    → nguy hiểm: gate nhận người vào nhiều hơn hàng thật → dồn lock vô ích
 *    → phải tự sửa.
 *  - Chiều gate < DB (hold sống chưa nhả — reserved đếm trong DB nhưng suất
 *    đã debit ở gate) là TRẠNG THÁI BÌNH THƯỜNG → tự nâng sẽ phá invariant,
 *    CMS nhập thêm hàng thì chủ động chạy lại flash-gate:seed.
 * Đọc DB không lock (chạy được trên replica) — clamp lệch nhẹ do race chỉ
 * theo chiều an toàn, phút sau tự cân tiếp.
 */
class FlashGateReconcileCommand extends Command
{
    protected $signature = 'flash-gate:reconcile';

    protected $description = 'Clamp quota gate xuống theo sellable DB (chống leak) — chạy mỗi phút khi có gate';

    public function handle(
        FlashGateService $gate,
        ProductStockRepositoryInterface $stockRepo,
        WarehouseService $warehouses,
    ): int {
        $ids = $gate->gatedVariantIds();
        if ($ids === []) {
            return self::SUCCESS; // thoát nhanh — schedule mỗi phút, đừng tốn query
        }

        $warehouseIds = $warehouses->sellableWarehouseIds();
        $warnAt = (int) config('flash_gate.reconcile_drift_warn', 5);

        foreach ($ids as $variantId) {
            $dbSellable = (int) $stockRepo->sellableProductStocks($variantId, $warehouseIds)
                ->sum(fn ($s) => max(0, (int) $s->on_hand - (int) ($s->reserved ?? 0)));

            $cut = $gate->clampDown($variantId, $dbSellable);
            if ($cut === null || $cut === 0) {
                continue;
            }

            $msg = sprintf('FlashGate reconcile: variant %d clamp -%d (→ %d theo DB)', $variantId, $cut, $dbSellable);
            $this->info($msg);
            if ($cut >= $warnAt) {
                Log::channel(config('flash_gate.log_channel'))->warning($msg . ' — drift lớn, nghi leak hoặc CMS sửa tồn chưa re-seed');
            }
        }

        return self::SUCCESS;
    }
}
