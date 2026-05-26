<?php

namespace App\Data\Output;

use App\Models\Entities\BlogCategory;
use Spatie\LaravelData\Data;

class BlogCategoryDTO extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $slug,
        public ?string $url,
    ) {}
    public static function fromModel(BlogCategory $category): self
    {
        $desc = $category->description;

        $title = (string) ($desc->title ?? '');
        $slug  = resolveSlug($desc->slug ?? null, $title);

        return new self(
            id: (int) $category->id,
            title: $title,
            slug: $slug,
            url: buildUrl($slug, getModuleConfig('url.blog_category'), (int) $category->id),
        );
    }
}
