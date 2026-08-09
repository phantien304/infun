<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Carrier;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CarrierRepositoryInterface;
use Illuminate\Support\Collection;

class CarrierRepository extends QueryableRepository implements CarrierRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Carrier::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.carriers'),
            fn () => $this->resetModel()
                ->orderBy('sort_order', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.carriers'));
    }
}
