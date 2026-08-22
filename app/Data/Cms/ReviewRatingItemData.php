<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewRating;
use Spatie\LaravelData\Data;

class ReviewRatingItemData extends Data
{
    public function __construct(
        public int $review_criteria_id,
        public string $code,
        public string $name,
        public int $rating,
    ) {
    }

    public static function fromModel(ReviewRating $m): self
    {
        $criteria = $m->reviewCriteria;
        $desc     = $criteria?->description;

        return new self(
            review_criteria_id: (int) $m->review_criteria_id,
            code: (string) ($criteria->code ?? ''),
            name: (string) ($desc->name ?? $criteria->code ?? ''),
            rating: (int) $m->rating,
        );
    }
}
