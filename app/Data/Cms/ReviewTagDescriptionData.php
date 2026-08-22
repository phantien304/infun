<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewTagDescription;
use Spatie\LaravelData\Data;

class ReviewTagDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
    ) {
    }

    public static function fromModel(ReviewTagDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
        );
    }
}
