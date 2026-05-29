<?php

namespace App\Data\Output;

use App\Models\Entities\Zone;
use Spatie\LaravelData\Data;

class ZoneDTO extends Data
{
    public function __construct(
        public int $id,
        public int $country_id,
        public ?string $name,
        public ?string $code,
        public ?string $ghnId,
        public ?string $ghnCode,
        public ?string $vtpId,
        public ?string $vtpCode,
        public ?string $sortOrder,
    ) {
    }

    public static function fromModel(Zone $zone): self
    {
        return new self(
            id: (int) $zone->id,
            country_id: (int) $zone->country_id,
            name: $zone->name,
            code: $zone->code,
            ghnId: $zone->ghn_id,
            ghnCode: $zone->ghn_code,
            vtpId: $zone->vtp_id,
            vtpCode: $zone->vtp_code,
            sortOrder: $zone->sort_order,
        );
    }
}
