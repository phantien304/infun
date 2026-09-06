<?php

namespace App\Data\Cms;

use App\Models\Entities\District;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class DistrictData extends Data
{
    public function __construct(
        public int $id,
        public ?int $zone_id,
        public ?string $code,
        public ?int $ghn_id,
        public ?string $name_ghn,
        public ?int $vtp_id,
        public ?string $vtp_value,
        public ?int $type,
        public ?int $support_type,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $district_descriptions,
    ) {
    }

    public static function fromModel(District $district): self
    {
        // name: ưu tiên attribute phẳng từ join của listForCms(); nhánh
        // show/update (relation descriptions đã load, không join) fallback
        // sang bản ghi đầu của descriptions.
        $description = $district->relationLoaded('descriptions')
            ? $district->descriptions->first()
            : null;

        return new self(
            id: (int) $district->id,
            zone_id: $district->zone_id !== null ? (int) $district->zone_id : null,
            code: $district->code,
            ghn_id: $district->ghn_id !== null ? (int) $district->ghn_id : null,
            name_ghn: $district->name_ghn,
            vtp_id: $district->vtp_id !== null ? (int) $district->vtp_id : null,
            vtp_value: $district->vtp_value,
            type: $district->type !== null ? (int) $district->type : null,
            support_type: $district->support_type !== null ? (int) $district->support_type : null,
            name: $district->name ?? $description?->name,
            deleted_at: $district->deleted_at?->toDateTimeString(),
            district_descriptions: $district->relationLoaded('descriptions')
                ? DistrictDescriptionData::collect($district->descriptions, Collection::class)
                : collect(),
        );
    }
}
