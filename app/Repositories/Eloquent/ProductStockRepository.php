<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ProductStock;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ProductStockRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductStockRepository extends QueryableRepository implements ProductStockRepositoryInterface
{
    public function model(): string
    {
        return ProductStock::class;
    }

    public function lockSellableProductStocks(int $variantId, array $warehouseIds): Collection
    {
        return $this->resetModel()
            ->where('product_variant_id', $variantId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->lockForUpdate()
            ->get();
    }

    public function sellableProductStocks(int $variantId, array $warehouseIds): Collection
    {
        return $this->resetModel()
            ->where('product_variant_id', $variantId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->get();
    }

    public function lockProductStock(int $variantId, int $warehouseId): ?ProductStock
    {
        return $this->resetModel()
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
    }

    public function save(ProductStock $stock): void
    {
        $stock->save();
    }

    public function updateOnHand(int $variantId, int $onHand, ?int $warehouseId = null): void
    {
        $warehouseId ??= app(\App\Services\Stock\WarehouseService::class)->defaultId();

        $stock = ProductStock::where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
        if ($stock) {
            $stock->on_hand = max(0, $onHand);
            $stock->version = (int) ($stock->version ?? 0) + 1;
            $stock->save();
        }
    }
}
