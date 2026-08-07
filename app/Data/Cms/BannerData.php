<?php

namespace App\Data\Cms;

use App\Models\Entities\Banner;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class BannerData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $position,
        public array $page,
        public int $sort_order,
        public ?string $theme,
        public ?string $title,
        public ?string $deleted_at,
        public Collection $banner_descriptions,
        public Collection $banner_values,
    ) {
    }

    public static function fromModel(Banner $banner): self
    {
        $title = $banner->title
            ?? ($banner->relationLoaded('descriptions')
                ? $banner->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $banner->id,
            type: (string) $banner->type,
            position: (string) $banner->position,
            page: $banner->page ? explode(',', $banner->page) : [],
            sort_order: (int) $banner->sort_order,
            theme: $banner->theme,
            title: $title,
            deleted_at: $banner->deleted_at?->toDateTimeString(),
            banner_descriptions: $banner->relationLoaded('descriptions')
                ? BannerDescriptionData::collect($banner->descriptions, Collection::class)
                : collect(),
            banner_values: $banner->relationLoaded('bannerValues')
                ? BannerValueData::collect($banner->bannerValues, Collection::class)
                : collect(),
        );
    }
}
