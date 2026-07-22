<?php

namespace App\Console\Commands;

use App\Enums\StockMovementType;
use App\Enums\StockPolicy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset tồn kho các sản phẩm dùng trong k6 (k6/mixed-30k.js) về trạng thái sạch
 * trước mỗi đợt tải.
 *
 * VẤN ĐỀ giải quyết (docs/CHANGELOG-2026-07-20 mục 8 "Phát hiện phụ"):
 * add-to-cart KHÔNG truyền variant_id → CartService luôn resolve về DEFAULT
 * variant (is_default=1). Chạy k6 nhiều lượt làm cạn on_hand của RIÊNG default
 * variant → mọi add trả 422 "hết hàng" dù các variant khác của cùng product vẫn
 * còn → số liệu sai (tưởng hạ tầng lỗi, thực ra hết hàng test data).
 *
 * Command này:
 *   - Product thường: on_hand default variant = --stock (mặc định 1.000.000),
 *     reserved=0, GIỮ policy Deny → vẫn đi ĐÚNG đường reserve/deduct lock
 *     (test tranh chấp thật), chỉ là không bao giờ cạn trong 1 đợt test.
 *   - Product flash: on_hand = --flash-stock (mặc định 1) → giữ khan hiếm cho
 *     kịch bản chống oversell.
 *
 * Idempotent — chạy lại bao nhiêu lần cũng ra cùng trạng thái. Ghi 1 row
 * stock_movement type=adjust (delta) để giữ bất biến "on_hand = SUM(movement)"
 * (xem CLAUDE.md cluster product_stock). Dùng DB::table (raw) — KHÔNG fire
 * observer (giống các seed command khác); page cache đã được k6 setup() ấm lại.
 *
 * Dùng:
 *   php artisan k6:provision-stock
 *   php artisan k6:provision-stock --products=3,6,9,14,17,19,21,27 --flash=730
 *   php artisan k6:provision-stock --products=1-50 --stock=500000
 */
class K6ProvisionStockCommand extends Command
{
    protected $signature = 'k6:provision-stock
        {--products=3,6,9,14,17,19,21,27 : Product ID set tồn cao (list "1,2,3" hoặc range "1-50", trộn được)}
        {--stock=1000000 : on_hand áp cho default variant của product thường}
        {--flash= : Product ID flash-sale set khan hiếm (list/range)}
        {--flash-stock=1 : on_hand áp cho default variant của product flash}
        {--warehouse= : warehouse_id (mặc định config_warehouse_id hoặc 1)}';

    protected $description = 'Reset tồn kho default variant của sản phẩm test k6 (chống cạn quantity qua nhiều lượt chạy).';

    public function handle(): int
    {
        $warehouseId = (int) ($this->option('warehouse') ?: (getConfigDb('config_warehouse_id') ?: 1));
        $regularIds  = $this->parseIds($this->option('products'));
        $flashIds    = $this->parseIds($this->option('flash'));
        $stock       = max(0, (int) $this->option('stock'));
        $flashStock  = max(0, (int) $this->option('flash-stock'));

        // flash thắng khi trùng id (khan hiếm có chủ đích).
        $regularIds = array_values(array_diff($regularIds, $flashIds));

        if (empty($regularIds) && empty($flashIds)) {
            $this->error('Không có product ID hợp lệ — truyền --products và/hoặc --flash.');

            return self::FAILURE;
        }

        $touched = 0;
        DB::transaction(function () use ($regularIds, $flashIds, $stock, $flashStock, $warehouseId, &$touched) {
            foreach ($regularIds as $productId) {
                $touched += $this->resetProduct($productId, $stock, $warehouseId);
            }
            foreach ($flashIds as $productId) {
                $touched += $this->resetProduct($productId, $flashStock, $warehouseId);
            }
        });

        $this->info(sprintf(
            'Xong: %d product_stock reset — thường %d SP @ on_hand=%d, flash %d SP @ on_hand=%d (kho %d).',
            $touched, count($regularIds), $stock, count($flashIds), $flashStock, $warehouseId,
        ));

        return self::SUCCESS;
    }

    /**
     * Reset stock của DEFAULT variant 1 product tại kho chỉ định.
     * Trả số row product_stock đã đụng (0 nếu product không có default variant).
     */
    private function resetProduct(int $productId, int $onHand, int $warehouseId): int
    {
        $variantId = (int) DB::table('product_variant')
            ->where('product_id', $productId)
            ->where('is_default', 1)
            ->whereNull('deleted_at')
            ->value('id');

        if ($variantId === 0) {
            $this->warn("  product {$productId}: không có default variant — bỏ qua (chạy simple-variants:seed trước?).");

            return 0;
        }

        $now   = Carbon::now();
        $stock = DB::table('product_stock')
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock === null) {
            DB::table('product_stock')->insert([
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'on_hand'            => $onHand,
                'reserved'           => 0,
                'subtract'           => 1,
                'inventory_policy'   => StockPolicy::Deny->value,
                'version'            => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
            $this->recordMovement($variantId, $warehouseId, $onHand, $onHand, $now);

            return 1;
        }

        $delta = $onHand - (int) $stock->on_hand;
        DB::table('product_stock')
            ->where('id', $stock->id)
            ->update([
                'on_hand'          => $onHand,
                'reserved'         => 0,   // xoá hold kẹt từ lượt test trước
                'inventory_policy' => StockPolicy::Deny->value,
                'version'          => DB::raw('version + 1'),
                'updated_at'       => $now,
            ]);
        $this->recordMovement($variantId, $warehouseId, $delta, $onHand, $now);

        return 1;
    }

    private function recordMovement(int $variantId, int $warehouseId, int $delta, int $onHandAfter, Carbon $now): void
    {
        DB::table('stock_movement')->insert([
            'product_variant_id' => $variantId,
            'warehouse_id'       => $warehouseId,
            'type'               => StockMovementType::Adjust->value,
            'quantity_change'    => $delta,
            'on_hand_after'      => $onHandAfter,
            'reference_type'     => 'k6_provision',
            'reference_id'       => null,
            'user_id'            => null,
            'note'               => 'k6:provision-stock reset',
            'created_at'         => $now,
        ]);
    }

    /**
     * "1,2,3" / "1-50" / trộn "1-5,9,20-22" → mảng int dương duy nhất, giữ thứ tự.
     */
    private function parseIds(?string $spec): array
    {
        if ($spec === null || trim($spec) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $spec) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_contains($part, '-')) {
                [$from, $to] = array_map('intval', explode('-', $part, 2));
                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }
                for ($i = $from; $i <= $to; $i++) {
                    if ($i > 0) {
                        $ids[$i] = $i;
                    }
                }
            } elseif (($id = (int) $part) > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
