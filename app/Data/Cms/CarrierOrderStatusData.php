<?php

namespace App\Data\Cms;

use App\Models\Entities\CarrierOrderStatus;
use Spatie\LaravelData\Data;

class CarrierOrderStatusData extends Data
{
    public function __construct(
        public int $id,
        public int $carrier_id,
        public ?string $carrier_name,
        public string $code,
        public string $name,
        public ?string $description,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(CarrierOrderStatus $status): self
    {
        return new self(
            id: (int) $status->id,
            carrier_id: (int) $status->carrier_id,
            carrier_name: $status->relationLoaded('carrier') ? $status->carrier?->name : null,
            code: (string) $status->code,
            name: (string) $status->name,
            description: $status->getAttribute('description'),
            deleted_at: $status->deleted_at?->toDateTimeString(),
        );
    }
}
