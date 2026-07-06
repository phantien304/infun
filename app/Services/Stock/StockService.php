<?php

namespace App\Services\Stock;

use App\Exceptions\InsufficientStockException;
use App\Models\Entities\ProductStock;
use App\Models\Entities\StockMovement;
use App\Models\Entities\StockReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nguồn duy nhất để thay đổi tồn kho có kiểm soát concurrency.
 *
 * Ba nhóm thao tác:
 *  - reserve / release   : giữ chỗ (hold) tồn cho một phiên checkout, có TTL.
 *  - deductForOrder      : trừ tồn thật khi tạo đơn, có GUARD chống oversell.
 *  - holderReservedMap   : trả về phần tồn mà chính phiên đang giữ, để lớp cart
 *                          cộng ngược lại (tránh phiên tự chặn chính mình).
 *
 * Mọi ghi vào product_stock đều đi qua lockForUpdate() trong transaction để
 * loại race condition khi nhiều request cùng chạm 1 variant lúc flash sale.
 */
class StockService
{
    private function warehouseId(): int
    {
        return (int) getCoreConfig('stock.default_warehouse_id');
    }

    private function policyDeny(): int
    {
        return (int) getCoreConfig('stock.policy.deny');
    }

    private function policyBackorder(): int
    {
        return (int) getCoreConfig('stock.policy.backorder');
    }

    private function policyUntracked(): int
    {
        return (int) getCoreConfig('stock.policy.untracked');
    }

    private function ttlMinutes(): int
    {
        return (int) (getCoreConfig('stock.reservation_ttl_minutes') ?: 15);
    }

    private function stockCheckoutEnabled(): bool
    {
        return (bool) getConfigDb('config_stock_checkout');
    }

    // =====================================================================
    // RESERVATION
    // =====================================================================

    /**
     * Giữ chỗ cho toàn bộ cart theo kiểu all-or-nothing trong 1 transaction.
     * Chỉ variant có policy DENY mới cần giữ (backorder/untracked luôn bán được).
     *
     * @param  array<int,array{product_variant_id?:int|null,quantity:int,name?:string}>  $items
     * @return array{ok:bool, failed?:array{name:string, available:int, requested:int}}
     */
    public function reserveCart(array $items, string $holder, ?int $userId): array
    {
        if (! $this->stockCheckoutEnabled()) {
            return ['ok' => true];
        }

        try {
            return DB::transaction(function () use ($items, $holder, $userId) {
                foreach ($items as $item) {
                    $variantId = (int) ($item['product_variant_id'] ?? 0);
                    $qty = (int) ($item['quantity'] ?? 0);
                    if ($variantId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $result = $this->reserveOne($holder, $userId, $variantId, $qty);
                    if (! ($result['ok'] ?? false)) {
                        // Ném để rollback các dòng đã giữ trong cùng lượt gọi này.
                        throw new ReservationUnavailable([
                            'name'      => (string) ($item['name'] ?? ''),
                            'available' => (int) ($result['available'] ?? 0),
                            'requested' => $qty,
                        ]);
                    }
                }

                return ['ok' => true];
            });
        } catch (ReservationUnavailable $e) {
            return ['ok' => false, 'failed' => $e->info];
        }
    }

    /**
     * Giữ chỗ cho 1 variant. Idempotent theo (holder, variant): gọi lại chỉ điều
     * chỉnh delta so với lượng phiên đang giữ và gia hạn TTL.
     *
     * @return array{ok:bool, available?:int}
     */
    private function reserveOne(string $holder, ?int $userId, int $variantId, int $wantQty): array
    {
        $warehouseId = $this->warehouseId();

        $stock = ProductStock::where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        // Không có row tồn → không quản kho ở đây; để guard lúc tạo đơn lo.
        if (! $stock) {
            return ['ok' => true];
        }

        $policy = (int) ($stock->inventory_policy ?? $this->policyDeny());
        // Backorder & untracked luôn bán được → không cần giữ chỗ.
        if ($policy !== $this->policyDeny()) {
            return ['ok' => true];
        }

        $existing = StockReservation::where('holder', $holder)
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $prev = (int) ($existing->quantity ?? 0);
        $onHand = (int) ($stock->on_hand ?? 0);
        $reserved = (int) ($stock->reserved ?? 0);

        // Tồn tối đa phiên NÀY có thể giữ = on_hand - (reserved của phiên khác).
        $maxForHolder = $onHand - ($reserved - $prev);

        if ($wantQty > $maxForHolder) {
            return ['ok' => false, 'available' => max(0, $maxForHolder)];
        }

        $delta = $wantQty - $prev;
        $expiresAt = Carbon::now()->addMinutes($this->ttlMinutes());

        if ($delta !== 0) {
            $stock->reserved = $reserved + $delta;
            $stock->version = (int) ($stock->version ?? 0) + 1;
            $stock->save();

            StockMovement::create([
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'type'               => (string) getCoreConfig(
                    $delta > 0 ? 'stock.movement_type.reserve' : 'stock.movement_type.release'
                ),
                'quantity_change'    => $delta,
                'on_hand_after'      => $onHand,
                'reference_type'     => 'reservation',
                'reference_id'       => null,
                'user_id'            => $userId ?: null,
                'note'               => 'hold='.$holder,
            ]);
        }

        if ($existing) {
            $existing->quantity = $wantQty;
            $existing->user_id = $userId ?: $existing->user_id;
            $existing->expires_at = $expiresAt;
            $existing->save();
        } else {
            StockReservation::create([
                'holder'             => $holder,
                'user_id'            => $userId ?: null,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'quantity'           => $wantQty,
                'expires_at'         => $expiresAt,
            ]);
        }

        return ['ok' => true];
    }

    /**
     * Nhả toàn bộ hold của 1 phiên (khi rời checkout / xoá cart / sau khi đặt đơn
     * xong với các dòng thừa). Trả về số row đã nhả.
     */
    public function releaseHolder(string $holder): int
    {
        return (int) DB::transaction(function () use ($holder) {
            $rows = StockReservation::where('holder', $holder)->get();
            $count = 0;
            foreach ($rows as $row) {
                $this->releaseReservationRow($row);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Nhả các hold đã hết hạn — gọi định kỳ bởi command stock:release-expired.
     * Trả về số hold đã nhả.
     */
    public function releaseExpired(int $limit = 500): int
    {
        $expired = StockReservation::whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($expired as $row) {
            DB::transaction(function () use ($row) {
                $this->releaseReservationRow($row);
            });
            $count++;
        }

        return $count;
    }

    /**
     * Nhả 1 row hold: product_stock.reserved -= quantity, ghi movement, xoá row.
     * Phải chạy trong transaction (caller đảm bảo).
     */
    private function releaseReservationRow(StockReservation $row): void
    {
        $variantId = (int) $row->product_variant_id;
        $warehouseId = (int) $row->warehouse_id;
        $qty = (int) $row->quantity;

        $stock = ProductStock::where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($stock && $qty > 0) {
            $onHand = (int) ($stock->on_hand ?? 0);
            $stock->reserved = max(0, (int) ($stock->reserved ?? 0) - $qty);
            $stock->version = (int) ($stock->version ?? 0) + 1;
            $stock->save();

            StockMovement::create([
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'type'               => (string) getCoreConfig('stock.movement_type.release'),
                'quantity_change'    => $qty,
                'on_hand_after'      => $onHand,
                'reference_type'     => 'reservation',
                'reference_id'       => null,
                'user_id'            => (int) ($row->user_id ?? 0) ?: null,
                'note'               => 'release hold='.$row->holder,
            ]);
        }

        $row->delete();
    }

    /**
     * Bản đồ [variant_id => quantity] mà 1 phiên đang giữ, dùng để cộng ngược
     * vào tồn khả bán khi hiển thị/validate cart (tránh phiên tự chặn chính mình).
     *
     * @return array<int,int>
     */
    public function holderReservedMap(string $holder): array
    {
        if ($holder === '') {
            return [];
        }

        return StockReservation::where('holder', $holder)
            ->pluck('quantity', 'product_variant_id')
            ->map(fn ($q) => (int) $q)
            ->all();
    }

    // =====================================================================
    // ORDER DEDUCTION (guarded)
    // =====================================================================

    /**
     * Trừ tồn thật cho 1 dòng đơn hàng — GUARD chống oversell.
     *
     * Phải được gọi BÊN TRONG transaction của việc tạo đơn: nếu ném
     * InsufficientStockException thì cả đơn rollback.
     *
     * @param  array{product_variant_id?:int|null, id?:int|string, quantity:int, order_id?:int|null}  $item
     */
    public function deductForOrder(array $item, string $holder, ?int $userId): void
    {
        $variantId = (int) ($item['product_variant_id'] ?? 0);
        $qty = (int) ($item['quantity'] ?? 0);

        if ($variantId <= 0) {
            logError(sprintf(
                'deductForOrder: order line for product %s has no product_variant_id; stock not decremented',
                $item['id'] ?? 'unknown',
            ));

            return;
        }

        $warehouseId = $this->warehouseId();

        $stock = ProductStock::where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            logError(sprintf(
                'deductForOrder: no product_stock row for variant %d; stock not decremented',
                $variantId,
            ));

            return;
        }

        $policy = (int) ($stock->inventory_policy ?? $this->policyDeny());
        if ($policy === $this->policyUntracked()) {
            $this->consumeHolderReservation($holder, $variantId, $warehouseId, $qty, (int) ($stock->on_hand ?? 0), $userId);

            return;
        }

        $onHand = (int) ($stock->on_hand ?? 0);
        $newOnHand = $onHand - $qty;

        // GUARD: chặn oversell cho policy DENY khi cửa hàng bật kiểm tra tồn.
        if ($newOnHand < 0 && $policy === $this->policyDeny() && $this->stockCheckoutEnabled()) {
            throw new InsufficientStockException($variantId, $qty, max(0, $onHand));
        }

        // Nhả phần hold của chính phiên này (nếu có) đồng thời với việc trừ tồn.
        $releasedReserved = $this->consumeHolderReservationQty($holder, $variantId, $warehouseId, $qty);

        $stock->on_hand = $newOnHand;
        $stock->reserved = max(0, (int) ($stock->reserved ?? 0) - $releasedReserved);
        $stock->version = (int) ($stock->version ?? 0) + 1;
        $stock->save();

        $isBackorder = $newOnHand < 0 && $policy === $this->policyBackorder();

        StockMovement::create([
            'product_variant_id' => $variantId,
            'warehouse_id'       => $warehouseId,
            'type'               => $isBackorder
                ? (string) getCoreConfig('stock.movement_type.sale_backorder')
                : (string) getCoreConfig('stock.movement_type.sale'),
            'quantity_change'    => -$qty,
            'on_hand_after'      => $newOnHand,
            'reference_type'     => 'order',
            'reference_id'       => $item['order_id'] ?? null,
            'user_id'            => $userId ?: null,
            'note'               => $isBackorder ? 'Sale exceeded on_hand — backorder backlog' : null,
        ]);
    }

    /**
     * Xoá row hold của phiên cho variant (nếu có) và trả về lượng đã giữ để
     * caller trừ khỏi product_stock.reserved. KHÔNG tự sửa reserved (caller làm).
     */
    private function consumeHolderReservationQty(string $holder, int $variantId, int $warehouseId, int $qty): int
    {
        if ($holder === '') {
            return 0;
        }

        $row = StockReservation::where('holder', $holder)
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (! $row) {
            return 0;
        }

        $held = (int) $row->quantity;
        $row->delete();

        // Chỉ nhả tối đa bằng lượng thực trừ để reserved không lệch âm.
        return min($held, max(0, $qty));
    }

    /**
     * Dùng cho nhánh untracked: chỉ dọn hold (nếu lỡ có) mà không đụng on_hand.
     */
    private function consumeHolderReservation(string $holder, int $variantId, int $warehouseId, int $qty, int $onHand, ?int $userId): void
    {
        $released = $this->consumeHolderReservationQty($holder, $variantId, $warehouseId, $qty);
        if ($released <= 0) {
            return;
        }

        $stock = ProductStock::where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
        if ($stock) {
            $stock->reserved = max(0, (int) ($stock->reserved ?? 0) - $released);
            $stock->save();
        }
    }
}
