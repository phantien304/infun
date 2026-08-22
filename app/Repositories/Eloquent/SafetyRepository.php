<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Safety;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\SafetyRepositoryInterface;

class SafetyRepository extends QueryableRepository implements SafetyRepositoryInterface
{
    public function model(): string
    {
        return Safety::class;
    }
}
