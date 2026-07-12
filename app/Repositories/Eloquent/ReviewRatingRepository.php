<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewRating;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewRatingRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReviewRatingRepository extends QueryableRepository implements ReviewRatingRepositoryInterface
{
    public function model(): string
    {
        return ReviewRating::class;
    }

    public function insert(array $rows): void
    {
        DB::table('review_rating')->insert($rows);
    }
}
