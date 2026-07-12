<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewMedia;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewMediaRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReviewMediaRepository extends QueryableRepository implements ReviewMediaRepositoryInterface
{
    public function model(): string
    {
        return ReviewMedia::class;
    }

    public function insert(array $rows): void
    {
        DB::table('review_media')->insert($rows);
    }
}
