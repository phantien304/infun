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

    public function upsertForReview(int $reviewId, array $ratings): void
    {
        if (empty($ratings)) {
            return;
        }

        $now  = now();
        $rows = array_map(fn ($r) => [
            'review_id'          => $reviewId,
            'review_criteria_id' => (int) $r['review_criteria_id'],
            'rating'             => max(1, min(5, (int) $r['rating'])),
            'created_at'         => $now,
            'updated_at'         => $now,
        ], $ratings);

        DB::table('review_rating')->upsert($rows, ['review_id', 'review_criteria_id'], ['rating', 'updated_at']);
    }
}
