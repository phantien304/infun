<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewTag;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class ReviewTagData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public int $usage_count,
        public bool $is_auto_generated,
        public bool $is_active,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $review_tag_descriptions,
    ) {
    }

    public static function fromModel(ReviewTag $tag): self
    {
        $name = $tag->name
            ?? ($tag->relationLoaded('descriptions')
                ? $tag->descriptions->first()?->name
                : null);

        return new self(
            id: (int) $tag->id,
            code: (string) $tag->code,
            usage_count: (int) $tag->usage_count,
            is_auto_generated: (bool) $tag->is_auto_generated,
            is_active: (bool) $tag->is_active,
            name: $name,
            deleted_at: $tag->deleted_at?->toDateTimeString(),
            review_tag_descriptions: $tag->relationLoaded('descriptions')
                ? ReviewTagDescriptionData::collect($tag->descriptions, Collection::class)
                : collect(),
        );
    }
}
