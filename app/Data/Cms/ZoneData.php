<?php

namespace App\Data\Cms;

use App\Models\Entities\Zone;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class ZoneData extends Data
{
    public function __construct(
        public int $id,
        public int $country_id,
        public ?string $code,
        public ?int $ghn_id,
        public ?int $ghn_code,
        public ?int $vtp_id,
        public ?string $vtp_code,
        public int $sort_order,
        public bool $status,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $zone_descriptions,
    ) {
    }

    public static function fromModel(Zone $zone): self
    {
        // name: ưu tiên attribute phẳng từ join của listForCms(); nhánh
        // show/update (relation descriptions đã load, không join) fallback
        // sang bản ghi đầu của descriptions — mirror CategoryData::fromModel.
        $description = $zone->relationLoaded('descriptions')
            ? $zone->descriptions->first()
            : null;

        return new self(
            id: (int) $zone->id,
            country_id: (int) $zone->country_id,
            code: $zone->code,
            ghn_id: $zone->ghn_id !== null ? (int) $zone->ghn_id : null,
            ghn_code: $zone->ghn_code !== null ? (int) $zone->ghn_code : null,
            vtp_id: $zone->vtp_id !== null ? (int) $zone->vtp_id : null,
            vtp_code: $zone->vtp_code,
            sort_order: (int) $zone->sort_order,
            status: (bool) $zone->status,
            name: $zone->name ?? $description?->name,
            deleted_at: $zone->deleted_at?->toDateTimeString(),
            zone_descriptions: $zone->relationLoaded('descriptions')
                ? ZoneDescriptionData::collect($zone->descriptions, Collection::class)
                : collect(),
        );
    }
}
