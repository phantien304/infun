<?php

namespace App\Data\Cms;

use App\Models\Entities\WardDescription;
use Spatie\LaravelData\Data;

class WardDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
    ) {
    }

    public static function fromModel(WardDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
        );
    }
}
