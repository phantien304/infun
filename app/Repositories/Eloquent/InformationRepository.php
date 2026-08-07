<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Information;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\InformationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class InformationRepository extends QueryableRepository implements InformationRepositoryInterface
{
    public function model(): string
    {
        return Information::class;
    }

    public function getDetail($id): ?Information
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()
            ->with('description')
            ->find($id);
    }

    public function listWithDescription(): Collection
    {
        return $this->resetModel()->with('description')->get();
    }
}
