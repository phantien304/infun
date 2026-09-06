<?php

namespace App\Data\Cms;

use App\Models\Entities\Country;
use Spatie\LaravelData\Data;

class CountryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $iso_code_2,
        public ?string $iso_code_3,
        public ?string $address_format,
        public bool $postcode_required,
        public bool $status,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Country $country): self
    {
        return new self(
            id: (int) $country->id,
            name: (string) $country->name,
            iso_code_2: $country->iso_code_2,
            iso_code_3: $country->iso_code_3,
            address_format: $country->address_format,
            postcode_required: (bool) $country->postcode_required,
            status: (bool) $country->status,
            deleted_at: $country->deleted_at?->toDateTimeString(),
        );
    }
}
