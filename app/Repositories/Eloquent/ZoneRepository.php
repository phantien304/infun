<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Zone;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ZoneRepository extends QueryableRepository implements ZoneRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Zone::class;
    }

    protected function baseQuery(): Builder
    {
        return $this->resetModel()->newQuery()
            ->where('country_id', getCoreConfig('zones.country_id_default'));
    }

    protected function withRelations(): array
    {
        return ['description'];
    }

    public function listAllCached(): Collection
    {
        return $this->rememberSystem(
            setting('cache.zones'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.zones'));
    }

    public function nameById(int $id): string
    {
        return (string) ($this->resetModel()->newQuery()
            ->withTrashed()
            ->with('description')
            ->find($id)
            ?->description?->name ?? '');
    }
}
