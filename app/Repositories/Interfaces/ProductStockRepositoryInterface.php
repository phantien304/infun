<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\ProductStock;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface ProductStockRepositoryInterface extends BaseRepositoryInterface
{
    public function lockSellableStocks(int $variantId, array $warehouseIds): EloquentCollection;

    public function lockStock(int $variantId, int $warehouseId): ?ProductStock;

    public function save(ProductStock $stock): void;
}
