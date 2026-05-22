<?php

namespace App\Data\Output;

use App\Models\Entities\Category;
use Spatie\LaravelData\Data;

class CategoryDTO extends Data
{
    public function __construct(
        public int $id,
        public int $parent_id,
        public int $sort_order,
        public ?string $image,
        public ?string $thumbnail,
        public ?string $icon,
        public string $title,
        public string $description,
        public string $slug,
        public string $url,
        public string $metaTitle,
        public string $metaDescription,
    ) {}
    public static function fromModel(Category $category): self
    {
        $desc = $category->description;

        $title = (string) ($desc->title ?? '');
        $slug  = resolveSlug($desc->slug ?? null, $title);

        return new self(
            id: (int) $category->id,
            parent_id: (int) $category->parent_id,
            sort_order: (int) $category->sort_order,
            image: $category->image,
            thumbnail: thumbnail($category->image, 400, 400, 'web'),
            icon: $category->icon,
            title: $title,
            description: (string) ($desc->description ?? ''),
            slug: $slug,
            url: buildUrl($slug, getModuleConfig('url.category'), (int) $category->id),
            metaTitle: (string) ($desc->meta_title ?? ''),
            metaDescription: (string) ($desc->meta_description ?? ''),
        );
    }
}
