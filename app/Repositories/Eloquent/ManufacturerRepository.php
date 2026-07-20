<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Manufacturer;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ManufacturerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ManufacturerRepository extends QueryableRepository implements ManufacturerRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Manufacturer::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberSystemModels(
            setting('cache.manufacturers'),
            fn () => $this->listAll()
        );
    }

    public function getManufacturerDetail(int $id): ?Manufacturer
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()->find($id);
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.manufacturers'));
    }

    public function getAll(): Collection
    {
        return $this->resetModel()->query()->get();
    }
}
