<?php

namespace App\Data\Cms;

use App\Models\Entities\ZoneToGeoZone;
use Spatie\LaravelData\Data;

class ZoneToGeoZoneData extends Data
{
    public function __construct(
        public int $id,
        public ?int $country_id,
        public ?int $zone_id,
    ) {
    }

    public static function fromModel(ZoneToGeoZone $item): self
    {
        return new self(
            id: (int) $item->id,
            country_id: $item->country_id !== null ? (int) $item->country_id : null,
            zone_id: $item->zone_id !== null ? (int) $item->zone_id : null,
        );
    }
}
