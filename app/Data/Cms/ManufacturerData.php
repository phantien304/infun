<?php

namespace App\Data\Cms;

use App\Models\Entities\Manufacturer;
use Spatie\LaravelData\Data;

class ManufacturerData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $image,
        public int $sort_order,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Manufacturer $manufacturer): self
    {
        return new self(
            id: (int) $manufacturer->id,
            name: (string) $manufacturer->name,
            image: $manufacturer->image,
            sort_order: (int) $manufacturer->sort_order,
            meta_title: $manufacturer->meta_title,
            meta_description: $manufacturer->meta_description,
            deleted_at: $manufacturer->deleted_at?->toDateTimeString(),
        );
    }
}
