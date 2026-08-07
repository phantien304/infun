<?php

namespace App\Data\Cms;

use App\Models\Entities\BannerValue;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class BannerValueData extends Data
{
    public function __construct(
        public int $id,
        public ?string $link,
        public int $sort_order,
        public string $media_type,
        public ?string $image,
        public ?string $video_provider,
        public ?string $video_url,
        public Collection $banner_value_descriptions,
    ) {
    }

    public static function fromModel(BannerValue $value): self
    {
        return new self(
            id: (int) $value->id,
            link: $value->link,
            sort_order: (int) $value->sort_order,
            media_type: $value->media_type ?: 'image',
            image: $value->image,
            video_provider: $value->video_provider,
            video_url: $value->video_url,
            banner_value_descriptions: $value->relationLoaded('descriptions')
                ? BannerValueDescriptionData::collect($value->descriptions, Collection::class)
                : collect(),
        );
    }
}
