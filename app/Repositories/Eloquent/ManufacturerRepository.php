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
        return $this->rememberCache(
            getCoreConfig('cache.manufacturers'),
            fn() => $this->listAll()
        );
    }
}
