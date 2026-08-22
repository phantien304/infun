<?php

namespace App\Data\Cms;

use App\Models\Entities\OptionDescription;
use Spatie\LaravelData\Data;

class OptionDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
        public ?string $name_display,
    ) {
    }

    public static function fromModel(OptionDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
            name_display: $d->name_display,
        );
    }
}
