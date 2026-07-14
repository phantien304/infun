<?php

namespace App\Services\Stock;

use App\Models\Entities\Warehouse;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;
use Illuminate\Support\Collection;

class WarehouseService
{
    private ?Collection $allMemo = null;

    public function __construct(protected WarehouseRepositoryInterface $warehouseRepo)
    {
    }

    public function allWarehouses(): Collection
    {
        return $this->allMemo ??= $this->warehouseRepo->listAllCached();
    }

    public function sellableWarehouseIds(): array
    {
        $ids = $this->allWarehouses()
            ->filter(fn (Warehouse $w) => $w->is_active && $w->is_sellable)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $ids !== [] ? $ids : [$this->defaultId()];
    }

    public function defaultId(): int
    {
        return (int) (getConfigDb('config_warehouse_id') ?: 1);
    }
}
