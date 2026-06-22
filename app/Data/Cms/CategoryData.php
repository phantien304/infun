<?php

namespace App\Data\Cms;

use App\Models\Entities\Category;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CategoryData extends Data
{
    public function __construct(
        public int $id,
        public int $parent_id,
        public int $sort_order,
        public ?string $icon,
        public ?string $image,
        public ?string $image_icon,
        public ?string $title,
        public ?string $deleted_at,
        #[DataCollectionOf(CategoryDescriptionData::class)]
        public Collection $category_descriptions,
    ) {
    }

    public static function fromModel(Category $category): self
    {
        // title: ưu tiên cột join (index); show thì lấy từ description đầu tiên.
        $title = $category->title
            ?? ($category->relationLoaded('descriptions')
                ? $category->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $category->id,
            parent_id: (int) $category->parent_id,
            sort_order: (int) $category->sort_order,
            icon: $category->icon,
            image: $category->image,
            image_icon: $category->image_icon,
            title: $title,
            deleted_at: $category->deleted_at?->toDateTimeString(),
            category_descriptions: $category->relationLoaded('descriptions')
                ? CategoryDescriptionData::collect($category->descriptions, Collection::class)
                : collect(),
        );
    }
}
