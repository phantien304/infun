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

    public function lockSellableStocks(int $variantId, array $warehouseIds): Collection
    {
        return $this->resetModel()
            ->where('product_variant_id', $variantId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->lockForUpdate()
            ->get();
    }

    public function lockStock(int $variantId, int $warehouseId): ?ProductStock
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
}
