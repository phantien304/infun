<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\ProductStock;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface ProductStockRepositoryInterface extends BaseRepositoryInterface
{
    public function lockSellableProductStocks(int $variantId, array $warehouseIds): EloquentCollection;

    public function lockProductStock(int $variantId, int $warehouseId): ?ProductStock;

    public function save(ProductStock $stock): void;

    public function updateOnHand(int $variantId, int $onHand, ?int $warehouseId = null): void;
}
