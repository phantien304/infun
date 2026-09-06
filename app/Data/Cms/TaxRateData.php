<?php

namespace App\Data\Cms;

use App\Models\Entities\TaxRate;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class TaxRateData extends Data
{
    public function __construct(
        public int $id,
        public ?int $geo_zone_id,
        public ?string $geo_zone_name,
        public ?string $name,
        public ?string $rate,
        public ?string $type,
        public ?string $deleted_at,
        public Collection $tax_rate_to_user_groups,
    ) {
    }

    public static function fromModel(TaxRate $taxRate): self
    {
        return new self(
            id: (int) $taxRate->id,
            geo_zone_id: $taxRate->geo_zone_id !== null ? (int) $taxRate->geo_zone_id : null,
            geo_zone_name: $taxRate->geo_zone_name ?? $taxRate->geoZone?->name,
            name: $taxRate->name,
            rate: $taxRate->rate !== null ? (string) $taxRate->rate : null,
            type: $taxRate->type,
            deleted_at: $taxRate->deleted_at?->toDateTimeString(),
            tax_rate_to_user_groups: $taxRate->relationLoaded('taxRateToUserGroups')
                ? $taxRate->taxRateToUserGroups->pluck('user_group_id')->map(fn ($id) => (int) $id)
                : collect(),
        );
    }
}
