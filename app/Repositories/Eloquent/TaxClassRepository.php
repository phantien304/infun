<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\TaxClass;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\TaxClassRepositoryInterface;
use Illuminate\Support\Collection;

class TaxClassRepository extends QueryableRepository implements TaxClassRepositoryInterface
{
    public function model(): string
    {
        return TaxClass::class;
    }

    public function getAll(): Collection
    {
        return TaxClass::query()->get();
    }
}
