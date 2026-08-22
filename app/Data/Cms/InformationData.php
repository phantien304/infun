<?php

namespace App\Data\Cms;

use App\Models\Entities\Information;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class InformationData extends Data
{
    public function __construct(
        public int $id,
        public ?int $banner_id,
        public int $sort_order,
        public ?string $title,
        public ?string $deleted_at,
        public Collection $information_descriptions,
    ) {
    }

    public static function fromModel(Information $information): self
    {
        $title = $information->title
            ?? ($information->relationLoaded('descriptions')
                ? $information->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $information->id,
            banner_id: $information->banner_id !== null ? (int) $information->banner_id : null,
            sort_order: (int) $information->sort_order,
            title: $title,
            deleted_at: $information->deleted_at?->toDateTimeString(),
            information_descriptions: $information->relationLoaded('descriptions')
                ? InformationDescriptionData::collect($information->descriptions, Collection::class)
                : collect(),
        );
    }
}
