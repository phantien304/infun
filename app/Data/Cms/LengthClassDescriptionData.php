<?php

namespace App\Data\Cms;

use App\Models\Entities\LengthClassDescription;
use Spatie\LaravelData\Data;

class LengthClassDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $title,
        public ?string $unit,
    ) {
    }

    public static function fromModel(LengthClassDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            title: $d->title,
            unit: $d->unit,
        );
    }
}
