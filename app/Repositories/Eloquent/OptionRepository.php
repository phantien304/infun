<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Option;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OptionRepositoryInterface;
use Illuminate\Support\Collection;

class OptionRepository extends QueryableRepository implements OptionRepositoryInterface
{
    public function model(): string
    {
        return Option::class;
    }

    public function listWithValues(): Collection
    {
        return Option::with(['description', 'optionValues.description'])->get();
    }
}
