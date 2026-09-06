<?php

namespace App\Data\Cms;

use App\Models\Entities\BlogTag;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class BlogTagData extends Data
{
    public function __construct(
        public int $id,
        public ?string $background,
        public int $sort_order,
        public ?string $title,
        public ?string $deleted_at,
        public Collection $blog_tag_descriptions,
    ) {
    }

    public static function fromModel(BlogTag $tag): self
    {
        $title = $tag->title
            ?? ($tag->relationLoaded('descriptions')
                ? $tag->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $tag->id,
            background: $tag->background,
            sort_order: (int) $tag->sort_order,
            title: $title,
            deleted_at: $tag->deleted_at?->toDateTimeString(),
            blog_tag_descriptions: $tag->relationLoaded('descriptions')
                ? BlogTagDescriptionData::collect($tag->descriptions, Collection::class)
                : collect(),
        );
    }
}
