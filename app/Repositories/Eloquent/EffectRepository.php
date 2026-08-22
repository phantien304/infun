<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Effect;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\EffectRepositoryInterface;

class EffectRepository extends QueryableRepository implements EffectRepositoryInterface
{
    public function model(): string
    {
        return Effect::class;
    }
}
