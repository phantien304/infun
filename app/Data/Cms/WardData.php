<?php

namespace App\Data\Cms;

use App\Models\Entities\Ward;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class WardData extends Data
{
    public function __construct(
        public int $id,
        public ?int $district_id,
        public ?string $ghn_id,
        public ?int $vtp_id,
        public ?string $name_vtp,
        public ?string $name_ghn,
        public ?string $note,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $ward_descriptions,
    ) {
    }

    public static function fromModel(Ward $ward): self
    {
        // name: ưu tiên attribute phẳng từ join của listForCms(); nhánh
        // show/update (relation descriptions đã load, không join) fallback
        // sang bản ghi đầu của descriptions.
        $description = $ward->relationLoaded('descriptions')
            ? $ward->descriptions->first()
            : null;

        return new self(
            id: (int) $ward->id,
            district_id: $ward->district_id !== null ? (int) $ward->district_id : null,
            ghn_id: $ward->ghn_id,
            vtp_id: $ward->vtp_id !== null ? (int) $ward->vtp_id : null,
            name_vtp: $ward->name_vtp,
            name_ghn: $ward->name_ghn,
            note: $ward->note,
            name: $ward->name ?? $description?->name,
            deleted_at: $ward->deleted_at?->toDateTimeString(),
            ward_descriptions: $ward->relationLoaded('descriptions')
                ? WardDescriptionData::collect($ward->descriptions, Collection::class)
                : collect(),
        );
    }
}
