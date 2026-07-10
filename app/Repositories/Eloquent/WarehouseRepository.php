<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Warehouse;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;
use Illuminate\Support\Collection;

class WarehouseRepository extends QueryableRepository implements WarehouseRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Warehouse::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.warehouses'),
            fn () => $this->resetModel()->orderBy('priority')->orderBy('id')->get(),
            perLocale: false,
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.warehouses'), perLocale: false);
    }
}
