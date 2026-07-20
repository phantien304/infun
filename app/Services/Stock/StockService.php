<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Enums\StockPolicy;
use App\Exceptions\InsufficientStockException;
use App\Helpers\ConcurrencyRetry;
use App\Models\Entities\ProductStock;
use App\Models\Entities\StockReservation;
use App\Repositories\Interfaces\ProductStockRepositoryInterface;
use App\Repositories\Interfaces\StockMovementRepositoryInterface;
use App\Repositories\Interfaces\StockReservationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(
        protected ProductStockRepositoryInterface $productStockRepo,
        protected StockReservationRepositoryInterface $stockReservationRepo,
        protected StockMovementRepositoryInterface $stockMovementRepo,
        protected WarehouseService $warehouseService,
        protected FlashGateService $flashGate,
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

    private function lockSellableProductStocks(int $variantId): Collection
    {
        $ids = $this->warehouseService->sellableWarehouseIds();

        $productStocks = $this->productStockRepo->lockSellableProductStocks($variantId, $ids);

        $flipIds = array_flip($ids);

        return $productStocks->sortBy(fn (ProductStock $s) => $flipIds[(int) $s->warehouse_id] ?? PHP_INT_MAX)->values();
    }

    private function effectiveStockPolicy(Collection $stocks): StockPolicy
    {
        $row = $stocks->firstWhere('warehouse_id', $this->warehouseService->defaultId())
            ?? $stocks->first();

        return $row?->policy() ?? StockPolicy::Deny;
    }

    public function reserveCheckout(array $items, string $sessionHolder, ?int $userId): array
    {
        if (! $this->stockCheckoutEnabled()) {
            return ['ok' => true];
        }

        // ── Flash-gate pre-pass (docs/FLASH-GATE.md Phase 1) ──
        // Admission TRƯỚC khi mở transaction: variant được seed mà hết suất →
        // từ chối ngay bằng Redis (~µs), không mở transaction / không xếp hàng
        // FOR UPDATE trên row product_stock. Debit theo DELTA so với hold đang
        // có để bấm lại / đổi qty không bị double-debit. Fail thì hoàn các
        // debit đã lấy trong pre-pass. Variant không gated / Redis lỗi →
        // fail-open, đi thẳng đường DB như cũ.
        $gateDebits = $this->flashGatePrePass($items, $sessionHolder);
        if (isset($gateDebits['failed'])) {
            return ['ok' => false, 'failed' => $gateDebits['failed']];
        }

        try {
            // ConcurrencyRetry: 1205/1213 (lock dồn trên product_stock) →
            // rollback + jitter 50-150ms + thử lại tối đa 2 lần, thay vì 5xx.
            return ConcurrencyRetry::run(fn () => $this->productStockRepo->transaction(function () use ($items, $sessionHolder, $userId) {
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
            }));
        } catch (ReservationUnavailable $e) {
            // DB (tầng chống oversell cuối) từ chối → hoàn suất gate đã debit
            $this->refundGateDebits($gateDebits['debits'] ?? []);

            return ['ok' => false, 'failed' => $e->info];
        } catch (\Illuminate\Database\QueryException $e) {
            // Hết retry vẫn kẹt lock (hoặc lỗi query khác) → hoàn suất gate.
            // Kẹt lock trả busy=true để controller hiện ErrorSystemBusy
            // thay vì báo "hết hàng" sai sự thật; lỗi khác ném tiếp.
            $this->refundGateDebits($gateDebits['debits'] ?? []);
            if (! ConcurrencyRetry::isLockContention($e)) {
                throw $e;
            }
            logError('reserveCheckout lock contention sau retry: ' . $e->getMessage());

            return ['ok' => false, 'busy' => true, 'failed' => [
                'name' => '', 'available' => 0, 'requested' => 0,
            ]];
        }
    }

    /**
     * Pre-pass gate cho reserveCheckout. Trả:
     *  - ['debits' => [variantId => qty đã debit]] khi qua hết (rỗng nếu gate
     *    tắt / không item nào gated),
     *  - ['failed' => [...]] cùng shape với ReservationUnavailable khi 1 item
     *    hết suất (các debit trước đó đã được hoàn bên trong).
     */
    private function flashGatePrePass(array $items, string $sessionHolder): array
    {
        if (! $this->flashGate->enabled()) {
            return ['debits' => []];
        }

        $debits = [];
        foreach ($items as $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }

            if ($this->flashGate->isGated($variantId) !== true) {
                continue;
            }

            $held = $this->holderHeldQty($sessionHolder, $variantId);
            $delta = $quantity - $held;

            if ($delta < 0) {
                $this->flashGate->release($variantId, -$delta);
                continue;
            }
            if ($delta === 0) {
                continue;
            }

            $acquired = $this->flashGate->tryAcquire($variantId, $delta);
            if ($acquired === false) {
                $this->refundGateDebits($debits);

                return ['failed' => [
                    'name'      => (string) ($item['name'] ?? ''),
                    'available' => max(0, $held + (int) ($this->flashGate->remaining($variantId) ?? 0)),
                    'requested' => $quantity,
                ]];
            }
            if ($acquired === true) {
                $debits[$variantId] = ($debits[$variantId] ?? 0) + $delta;
            }
            // null = key vừa teardown / Redis lỗi → fail-open
        }

        return ['debits' => $debits];
    }

    private function holderHeldQty(string $holder, int $variantId): int
    {
        if ($holder === '') {
            return 0;
        }

        return (int) $this->stockReservationRepo
            ->reservationsForVariant($holder, $variantId)
            ->sum('quantity');
    }

    private function refundGateDebits(array $debits): void
    {
        foreach ($debits as $variantId => $qty) {
            $this->flashGate->release((int) $variantId, (int) $qty);
        }
    }

    private function reserveOne(string $sessionHolder, ?int $userId, int $variantId, int $wantQuantity): array
    {
        $productStocks = $this->lockSellableProductStocks($variantId);

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
                    'type'               => $reservedDelta > 0 ? StockMovementType::Reserve->value : StockMovementType::Release->value,
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
                $this->releaseReservationRow($row, onlyIfExpired: true);
            });
            $count++;
        }

        return $count;
    }

    private function releaseReservationRow(StockReservation $row, bool $onlyIfExpired = false): void
    {
        $variantId = (int) $row->product_variant_id;
        $warehouseId = (int) $row->warehouse_id;
        $productStock = $this->productStockRepo->lockProductStock($variantId, $warehouseId);
        $freshStockReservation = $this->stockReservationRepo->findReservationById((int) $row->id);
        if (! $freshStockReservation) {
            return;
        }
        if ($onlyIfExpired && ! $freshStockReservation->isExpired()) {
            return;
        }

        $quantity = (int) $freshStockReservation->quantity;
        if ($this->stockReservationRepo->deleteReservationById((int) $freshStockReservation->id) !== 1) {
            return;
        }

        // Flash gate: hold nhả ra (hủy giỏ / hết hạn) → trả suất admission,
        // credit CHỈ sau commit (rollback thì không trả nhầm). deductForOrder
        // cố ý KHÔNG credit — suất đó đã tiêu thụ thành hàng bán thật.
        if ($quantity > 0) {
            DB::afterCommit(fn () => $this->flashGate->release($variantId, $quantity));
        }

        if ($productStock && $quantity > 0) {
            $onHand = (int) ($productStock->on_hand ?? 0);
            $productStock->reserved = max(0, (int) ($productStock->reserved ?? 0) - $quantity);
            $productStock->version = (int) ($productStock->version ?? 0) + 1;
            $this->productStockRepo->save($productStock);

            $this->stockMovementRepo->create([
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'type'               => StockMovementType::Release->value,
                'quantity_change'    => $quantity,
                'on_hand_after'      => $onHand,
                'reference_type'     => 'reservation',
                'reference_id'       => null,
                'user_id'            => (int) ($freshStockReservation->user_id ?? 0) ?: null,
                'note'               => 'release hold='.$freshStockReservation->holder,
            ]);
        }
    }

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
        $quantity = (int) ($item['quantity'] ?? 0);

        if ($variantId <= 0) {
            logError(sprintf(
                'deductForOrder: order line for product %s has no product_variant_id; stock not decremented',
                $item['id'] ?? 'unknown',
            ));

            return;
        }

        $productStocks = $this->lockSellableProductStocks($variantId);

        if ($productStocks->isEmpty()) {
            logError(sprintf(
                'deductForOrder: no product_stock row for variant %d; stock not decremented',
                $variantId,
            ));

            return;
        }

        $policy = $this->effectiveStockPolicy($productStocks);

        if ($policy === StockPolicy::Untracked) {
            $this->consumeAllHolderReservations($sessionHolder, $variantId, $productStocks);

            return;
        }

        $stockReservations = $this->stockReservationRepo->reservationsForVariant($sessionHolder, $variantId)
            ->keyBy('warehouse_id');

        $holderAvailableClosure = fn (ProductStock $s): int => max(
            0,
            (int) $s->on_hand - ((int) ($s->reserved ?? 0) - (int) ($stockReservations->get((int) $s->warehouse_id)?->quantity ?? 0))
        );

        $availableForHolder = (int) $productStocks->sum($holderAvailableClosure);

        if ($quantity > $availableForHolder && $policy === StockPolicy::Deny && $this->stockCheckoutEnabled()) {
            throw new InsufficientStockException($variantId, $quantity, max(0, $availableForHolder));
        }

        $ordered = $productStocks
            ->sortBy(fn (ProductStock $s, int $i) => [$stockReservations->has((int) $s->warehouse_id) ? 0 : 1, $i])
            ->values();

        $remaining = $quantity;
        foreach ($ordered as $stock) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $holderAvailableClosure($stock));
            if ($take <= 0) {
                continue;
            }

            $this->applyDeduction($stock, $take, $stockReservations, $sessionHolder, $item, $userId, StockMovementType::Sale);
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $target = $productStocks->firstWhere('warehouse_id', $this->warehouseService->defaultId())
                ?? $productStocks->first();

            $type = $policy === StockPolicy::Backorder
                ? StockMovementType::SaleBackorder
                : StockMovementType::Sale;

            $this->applyDeduction($target, $remaining, $stockReservations, $sessionHolder, $item, $userId, $type);
        }
    }

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

        $released = 0;
        $hold = $holds->get($warehouseId);
        if ($holder !== '' && $hold) {
            if ($this->stockReservationRepo->deleteReservationById((int) $hold->id) === 1) {
                $released = (int) $hold->quantity;
            }
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
            if ($this->stockReservationRepo->deleteReservationById((int) $hold->id) !== 1) {
                continue;
            }
            $stock = $stocks->firstWhere('warehouse_id', (int) $hold->warehouse_id);
            if ($stock) {
                $stock->reserved = max(0, (int) ($stock->reserved ?? 0) - (int) $hold->quantity);
                $this->productStockRepo->save($stock);
            }
        }
    }
}
