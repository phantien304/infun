<?php

namespace App\Data\Output;

use App\Models\Entities\ReviewRating;
use Spatie\LaravelData\Data;

class ReviewRatingDTO extends Data
{
    public function __construct(
        public int $reviewCriteriaId,
        public string $code,
        public string $criteriaName,
        public int $rating,
    ) {
    }

    public static function fromModel(ReviewRating $m): self
    {
        $criteria = $m->reviewCriteria;
        $desc     = $criteria?->description;
        return new self(
            reviewCriteriaId: (int) $m->review_criteria_id,
            code:             (string) ($criteria->code ?? ''),
            criteriaName:     (string) ($desc->name ?? $criteria->code ?? ''),
            rating:           (int) $m->rating,
        );
    }
}
