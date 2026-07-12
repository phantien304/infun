<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Attribute;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AttributeRepositoryInterface;
use Illuminate\Support\Collection;

class AttributeRepository extends QueryableRepository implements AttributeRepositoryInterface
{
    public function model(): string
    {
        return Attribute::class;
    }

    public function listWithValues(): Collection
    {
        return Attribute::with(['description', 'attributeValues.description'])->get();
    }
}
