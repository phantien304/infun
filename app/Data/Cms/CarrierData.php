<?php

namespace App\Data\Cms;

use App\Models\Entities\Carrier;
use Spatie\LaravelData\Data;

class CarrierData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $image,
        public int $sort_order,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Carrier $carrier): self
    {
        return new self(
            id: (int) $carrier->id,
            code: (string) $carrier->code,
            name: (string) $carrier->name,
            image: $carrier->image,
            sort_order: (int) $carrier->sort_order,
            deleted_at: $carrier->deleted_at?->toDateTimeString(),
        );
    }
}
