<?php

namespace App\Data\Cms;

use App\Models\Entities\StockStatusDescription;
use Spatie\LaravelData\Data;

class StockStatusDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
    ) {
    }

    public static function fromModel(StockStatusDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
        );
    }
}
