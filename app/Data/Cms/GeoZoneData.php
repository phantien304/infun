<?php

namespace App\Data\Cms;

use App\Models\Entities\GeoZone;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class GeoZoneData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public ?string $deleted_at,
        public Collection $zone_to_geo_zones,
    ) {
    }

    public static function fromModel(GeoZone $geoZone): self
    {
        return new self(
            id: (int) $geoZone->id,
            name: $geoZone->name,
            description: $geoZone->description,
            deleted_at: $geoZone->deleted_at?->toDateTimeString(),
            zone_to_geo_zones: $geoZone->relationLoaded('zoneToGeoZones')
                ? ZoneToGeoZoneData::collect($geoZone->zoneToGeoZones, Collection::class)
                : collect(),
        );
    }
}
