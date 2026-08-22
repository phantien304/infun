<?php

namespace App\Data\Cms;

use App\Models\Entities\FilterDescription;
use Spatie\LaravelData\Data;

class FilterDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
    ) {
    }

    public static function fromModel(FilterDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
        );
    }
}
