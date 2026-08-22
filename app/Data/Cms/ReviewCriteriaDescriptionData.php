<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewCriteriaDescription;
use Spatie\LaravelData\Data;

class ReviewCriteriaDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
        public ?string $hint,
    ) {
    }

    public static function fromModel(ReviewCriteriaDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
            hint: $d->hint,
        );
    }
}
