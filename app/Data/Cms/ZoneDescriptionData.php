<?php

namespace App\Data\Cms;

use App\Models\Entities\ZoneDescription;
use Spatie\LaravelData\Data;

class ZoneDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
    ) {
    }

    public static function fromModel(ZoneDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
        );
    }
}
