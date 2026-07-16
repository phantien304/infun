<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Ward;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\WardRepositoryInterface;
use Illuminate\Support\Collection;

class WardRepository extends QueryableRepository implements WardRepositoryInterface
{
    public function model(): string
    {
        return Ward::class;
    }

    public function listByDistrict(int $districtId): Collection
    {
        return $this->resetModel()->query()
            ->where('district_id', $districtId)
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
