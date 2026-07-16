<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\District;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\DistrictRepositoryInterface;
use Illuminate\Support\Collection;

class DistrictRepository extends QueryableRepository implements DistrictRepositoryInterface
{
    public function model(): string
    {
        return District::class;
    }

    public function listByZone(int $zoneId): Collection
    {
        return $this->resetModel()->query()
            ->where('zone_id', $zoneId)
            ->with('description')
            ->orderBy('id')
            ->get();
    }

    public function nameById(int $id): string
    {
        return (string) ($this->resetModel()
            ->withTrashed()
            ->with('description')
            ->find($id)
            ?->description?->name ?? '');
    }
}
