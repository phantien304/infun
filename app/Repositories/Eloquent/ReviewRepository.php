<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Review;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewRepositoryInterface;

class ReviewRepository extends QueryableRepository implements ReviewRepositoryInterface
{
    public function model(): string
    {
        return Review::class;
    }
}
