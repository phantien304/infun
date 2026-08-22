<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Skincare;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\SkincareRepositoryInterface;

class SkincareRepository extends QueryableRepository implements SkincareRepositoryInterface
{
    public function model(): string
    {
        return Skincare::class;
    }
}
