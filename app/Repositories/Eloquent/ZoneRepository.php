<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Zone;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ZoneRepository extends QueryableRepository implements ZoneRepositoryInterface
{
    use CacheableRepository;
    public function model(): string
    {
        return Zone::class;
    }
    protected function baseQuery(): Builder
    {
        return $this->model->newQuery()
            ->where('country_id', getCoreConfig('zones.country_id_default'));
    }
    protected function withRelations(): array
    {
        return ['description'];
    }
    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.zones') . app()->getLocale(),
            fn() => $this->listAll()
        );
    }
}
