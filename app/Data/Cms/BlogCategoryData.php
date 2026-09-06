<?php

namespace App\Data\Cms;

use App\Models\Entities\BlogCategory;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class BlogCategoryData extends Data
{
    public function __construct(
        public int $id,
        public ?int $parent_id,
        public ?int $banner_id,
        public ?string $icon,
        public ?string $image,
        public ?string $title,
        public ?string $deleted_at,
        public Collection $blog_category_descriptions,
    ) {
    }

    public static function fromModel(BlogCategory $category): self
    {
        $title = $category->title
            ?? ($category->relationLoaded('descriptions')
                ? $category->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $category->id,
            parent_id: $category->parent_id !== null ? (int) $category->parent_id : null,
            banner_id: $category->banner_id !== null ? (int) $category->banner_id : null,
            icon: $category->icon,
            image: $category->image,
            title: $title,
            deleted_at: $category->deleted_at?->toDateTimeString(),
            blog_category_descriptions: $category->relationLoaded('descriptions')
                ? BlogCategoryDescriptionData::collect($category->descriptions, Collection::class)
                : collect(),
        );
    }
}
