<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Enums\StockPolicy;
use App\Exceptions\InsufficientStockException;
use App\Models\Entities\ProductStock;
use App\Models\Entities\StockReservation;
use App\Repositories\Interfaces\ProductStockRepositoryInterface;
use App\Repositories\Interfaces\StockMovementRepositoryInterface;
use App\Repositories\Interfaces\StockReservationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class StockService
{
    public function __construct(
        protected ProductStockRepositoryInterface $productStockRepo,
        protected StockReservationRepositoryInterface $stockReservationRepo,
        protected StockMovementRepositoryInterface $stockMovementRepo,
        protected WarehouseService $warehouseService,
    ) {
    }

    private function ttlMinutes(): int
    {
        return (int) (getCoreConfig('stock.reservation_ttl_minutes') ?: 15);
    }

    private function stockCheckoutEnabled(): bool
    {
        return (bool) getConfigDb('config_stock_checkout');
    }

    private function lockSellableStocks(int $variantId): Collection
    {
        $ids = $this->warehouseService->sellableIds();

        $productStocks = $this->productStockRepo->lockSellableStocks($variantId, $ids);

        $flipIds = array_flip($ids);

        return $productStocks->sortBy(fn (ProductStock $s) => $flipIds[(int) $s->warehouse_id] ?? PHP_INT_MAX)->values();
    }

    private function effectiveStockPolicy(Collection $stocks): StockPolicy
    {
        $row = $stocks->firstWhere('warehouse_id', $this->warehouseService->defaultId())
            ?? $stocks->first();

        return $row?->policy() ?? StockPolicy::Deny;
    }

    public function reserveCart(array $items, string $sessionHolder, ?int $userId): array
    {
        if (! $this->stockCheckoutEnabled()) {
            return ['ok' => true];
        }

        try {
            return $this->productStockRepo->transaction(function () use ($items, $sessionHolder, $userId) {
                foreach ($items as $item) {
                    $variantId = (int) ($item['product_variant_id'] ?? 0);
                    $quantity = (int) ($item['quantity'] ?? 0);
                    if ($variantId <= 0 || $quantity <= 0) {
                        continue;
                    }

                    $result = $this->reserveOne($sessionHolder, $userId, $variantId, $quantity);
                    if (! ($result['ok'] ?? false)) {
                        throw new ReservationUnavailable([
                            'name'      => (string) ($item['name'] ?? ''),
                            'available' => (int) ($result['available'] ?? 0),
                            'requested' => $quantity,
                        ]);
                    }
                }

                return ['ok' => true];
            });
        } catch (ReservationUnavailable $e) {
            return ['ok' => false, 'failed' => $e->info];
        }
    }

    private function reserveOne(string $sessionHolder, ?int $userId, int $variantId, int $wantQuantity): array
    {
        $productStocks = $this->lockSellableStocks($variantId);

        if ($productStocks->isEmpty()) {
            return ['ok' => true];
        }

        if ($this->effectiveStockPolicy($productStocks)->bypassesStockCheck()) {
            return ['ok' => true];
        }

        $stockReservations = $this->stockReservationRepo->reservationsForVariant($sessionHolder, $variantId)->keyBy('warehouse_id');

        $maxQtyForHolder = 0;
        foreach ($productStocks as $stock) {
            $alreadyHeldQuantity = (int) ($stockReservations->get((int) $stock->warehouse_id)?->quantity ?? 0);
            $maxQtyForHolder += max(0, (int) $stock->on_hand - ((int) $stock->reserved - $alreadyHeldQuantity));
        }

        if ($wantQuantity > $maxQtyForHolder) {
            return ['ok' => false, 'available' => max(0, $maxQtyForHolder)];
        }

        $remainingQuantity = $wantQuantity;
        $expiresAt = Carbon::now()->addMinutes($this->ttlMinutes());

        foreach ($productStocks as $stock) {
            $warehouseId = (int) $stock->warehouse_id;

            $existingHold = $stockReservations->get($warehouseId);
            $alreadyHeldQuantity = (int) ($existingHold?->quantity ?? 0);
            $availableToHold = max(0, (int) $stock->on_hand - ((int) $stock->reserved - $alreadyHeldQuantity));
            $quantityToHold = min($remainingQuantity, $availableToHold);
            $remainingQuantity -= $quantityToHold;

            $reservedDelta = $quantityToHold - $alreadyHeldQuantity;
            if ($reservedDelta !== 0) {
                $stock->reserved = (int) $stock->reserved + $reservedDelta;
                $stock->version = (int) ($stock->version ?? 0) + 1;
                $this->productStockRepo->save($stock);

                $this->stockMovementRepo->create([
                    'product_variant_id' => $variantId,
                    'warehouse_id'       => $warehouseId,
                    'type'               => $reservedDelta > 0 ? StockMovementType::Reserve : StockMovementType::Release,
                    'quantity_change'    => $reservedDelta,
                    'on_hand_after'      => (int) $stock->on_hand,
                    'reference_type'     => 'reservation',
                    'reference_id'       => null,
                    'user_id'            => $userId ?: null,
                    'note'               => 'hold='.$sessionHolder,
                ]);
            }

            if ($quantityToHold > 0) {
                if ($existingHold) {
                    $existingHold->quantity = $quantityToHold;
                    $existingHold->user_id = $userId ?: $existingHold->user_id;
                    $existingHold->expires_at = $expiresAt;
                    $this->stockReservationRepo->saveReservation($existingHold);
                } else {
                    $this->stockReservationRepo->createReservation([
                        'holder'             => $sessionHolder,
                        'user_id'            => $userId ?: null,
                        'product_variant_id' => $variantId,
                        'warehouse_id'       => $warehouseId,
                        'quantity'           => $quantityToHold,
                        'expires_at'         => $expiresAt,
                    ]);
                }
            } elseif ($existingHold) {
                $this->stockReservationRepo->deleteReservation($existingHold);
            }
        }

        return ['ok' => true];
    }

    public function releaseHolder(string $holder): int
    {
        return (int) $this->productStockRepo->transaction(function () use ($holder) {
            $rows = $this->stockReservationRepo->reservationsForHolder($holder);
            $count = 0;
            foreach ($rows as $row) {
                $this->releaseReservationRow($row);
                $count++;
            }

            return $count;
        });
    }

    public function releaseExpired(int $limit = 500): int
    {
        $expired = $this->stockReservationRepo->expiredReservations($limit);

        $count = 0;
        foreach ($expired as $row) {
            $this->productStockRepo->transaction(function () use ($row) {
                $this->releaseReservationRow($row);
            });
            $count++;
        }

        return $count;
    }

    private function releaseReservationRow(StockReservation $row): void
    {
        $variantId = (int) $row->product_variant_id;
        $warehouseId = (int) $row->warehouse_id;
        $qty = (int) $row->quantity;

        $productStock = $this->productStockRepo->lockStock($variantId, $warehouseId);

        if ($productStock && $qty > 0) {
            $onHand = (int) ($productStock->on_hand ?? 0);
            $productStock->reserved = max(0, (int) ($productStock->reserved ?? 0) - $qty);
            $productStock->version = (int) ($productStock->version ?? 0) + 1;
            $this->productStockRepo->save($productStock);

            $this->stockMovementRepo->create([
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'type'               => StockMovementType::Release,
                'quantity_change'    => $qty,
                'on_hand_after'      => $onHand,
                'reference_type'     => 'reservation',
                'reference_id'       => null,
                'user_id'            => (int) ($row->user_id ?? 0) ?: null,
                'note'               => 'release hold='.$row->holder,
            ]);
        }

        $this->stockReservationRepo->deleteReservation($row);
    }

    /**
     * Bản đồ [variant_id => quantity] mà 1 phiên đang giữ — SUM qua mọi kho
     * (một variant có thể được hold rải trên nhiều kho).
     *
     * @return array<int,int>
     */
    public function holderReservedMap(string $holder): array
    {
        if ($holder === '') {
            return [];
        }

        return $this->stockReservationRepo->holderReservedMap($holder);
    }

    public function deductForOrder(array $item, string $sessionHolder, ?int $userId): void
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

        $stocks = $this->lockSellableStocks($variantId);

        if ($stocks->isEmpty()) {
            logError(sprintf(
                'deductForOrder: no product_stock row for variant %d; stock not decremented',
                $variantId,
            ));

            return;
        }

        $policy = $this->effectiveStockPolicy($stocks);

        if ($policy === StockPolicy::Untracked) {
            $this->consumeAllHolderReservations($sessionHolder, $variantId, $stocks);

            return;
        }

        $totalAvailable = $stocks->sum(fn (ProductStock $s) => max(0, (int) $s->on_hand));

        // GUARD: chặn oversell cho policy DENY khi cửa hàng bật kiểm tra tồn.
        if ($qty > $totalAvailable && $policy === StockPolicy::Deny && $this->stockCheckoutEnabled()) {
            throw new InsufficientStockException($variantId, $qty, max(0, $totalAvailable));
        }

        $holds = $this->stockReservationRepo->reservationsForVariant($sessionHolder, $variantId)
            ->keyBy('warehouse_id');

        // Kho có hold của phiên đứng trước, trong mỗi nhóm giữ nguyên priority.
        $ordered = $stocks
            ->sortBy(fn (ProductStock $s, int $i) => [$holds->has((int) $s->warehouse_id) ? 0 : 1, $i])
            ->values();

        $remaining = $qty;
        foreach ($ordered as $stock) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, max(0, (int) $stock->on_hand));
            if ($take <= 0) {
                continue;
            }

            $this->applyDeduction($stock, $take, $holds, $sessionHolder, $item, $userId, StockMovementType::Sale);
            $remaining -= $take;
        }

        // Phần thiếu: backorder ghi âm vào kho mặc định (hoặc deny khi cửa hàng
        // tắt kiểm tra tồn — giữ hành vi cũ: vẫn trừ, chấp nhận âm).
        if ($remaining > 0) {
            $target = $stocks->firstWhere('warehouse_id', $this->warehouseService->defaultId())
                ?? $stocks->first();

            $type = $policy === StockPolicy::Backorder
                ? StockMovementType::SaleBackorder
                : StockMovementType::Sale;

            $this->applyDeduction($target, $remaining, $holds, $sessionHolder, $item, $userId, $type);
        }
    }

    /**
     * Trừ $take khỏi 1 row kho: dọn hold của phiên tại kho đó, cập nhật
     * on_hand/reserved, ghi movement. Row đã được lock từ trước.
     *
     * @param  \Illuminate\Support\Collection<int, StockReservation>  $holds  keyBy warehouse_id
     */
    private function applyDeduction(
        ProductStock $stock,
        int $take,
        $holds,
        string $holder,
        array $item,
        ?int $userId,
        StockMovementType $type,
    ): void {
        $warehouseId = (int) $stock->warehouse_id;

        // Nhả TOÀN BỘ hold của phiên tại kho này (không chỉ min(held, take)) —
        // hold tồn tại vì dòng đơn này; giữ phần dư là leak reserved vĩnh viễn.
        $released = 0;
        $hold = $holds->get($warehouseId);
        if ($holder !== '' && $hold) {
            $released = (int) $hold->quantity;
            $this->stockReservationRepo->deleteReservation($hold);
            $holds->forget($warehouseId);
        }

        $newOnHand = (int) $stock->on_hand - $take;
        $stock->on_hand = $newOnHand;
        $stock->reserved = max(0, (int) ($stock->reserved ?? 0) - $released);
        $stock->version = (int) ($stock->version ?? 0) + 1;
        $this->productStockRepo->save($stock);

        $this->stockMovementRepo->create([
            'product_variant_id' => (int) $stock->product_variant_id,
            'warehouse_id'       => $warehouseId,
            'type'               => $type,
            'quantity_change'    => -$take,
            'on_hand_after'      => $newOnHand,
            'reference_type'     => 'order',
            'reference_id'       => $item['order_id'] ?? null,
            'user_id'            => $userId ?: null,
            'note'               => $type === StockMovementType::SaleBackorder
                ? 'Sale exceeded on_hand — backorder backlog'
                : null,
        ]);
    }

    private function consumeAllHolderReservations(string $holder, int $variantId, Collection $stocks): void
    {
        if ($holder === '') {
            return;
        }

        $holds = $this->stockReservationRepo->reservationsForVariant($holder, $variantId);

        foreach ($holds as $hold) {
            $stock = $stocks->firstWhere('warehouse_id', (int) $hold->warehouse_id);
            if ($stock) {
                $stock->reserved = max(0, (int) ($stock->reserved ?? 0) - (int) $hold->quantity);
                $this->productStockRepo->save($stock);
            }
            $this->stockReservationRepo->deleteReservation($hold);
        }
    }
}
