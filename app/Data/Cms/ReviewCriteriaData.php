<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewCriteria;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class ReviewCriteriaData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public ?string $icon,
        public int $sort_order,
        public bool $is_required,
        public bool $is_active,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $review_criteria_descriptions,
    ) {
    }

    public static function fromModel(ReviewCriteria $criteria): self
    {
        $name = $criteria->name
            ?? ($criteria->relationLoaded('descriptions')
                ? $criteria->descriptions->first()?->name
                : null);

        return new self(
            id: (int) $criteria->id,
            code: (string) $criteria->code,
            icon: $criteria->icon,
            sort_order: (int) $criteria->sort_order,
            is_required: (bool) $criteria->is_required,
            is_active: (bool) $criteria->is_active,
            name: $name,
            deleted_at: $criteria->deleted_at?->toDateTimeString(),
            review_criteria_descriptions: $criteria->relationLoaded('descriptions')
                ? ReviewCriteriaDescriptionData::collect($criteria->descriptions, Collection::class)
                : collect(),
        );
    }
}
