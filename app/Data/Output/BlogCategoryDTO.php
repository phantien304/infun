<?php

namespace App\Data\Output;

use App\Models\Entities\BlogCategory;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

class BlogCategoryDTO extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $url,
    ) {}
    public static function fromModel(BlogCategory $category): self
    {
        $desc = $category->description;

        $title = (string) ($desc->title ?? '');
        $slug  = (string) ($desc->slug ?? Str::slug($title));

        return new self(
            id: (int) $category->id,
            title: $title,
            slug: $slug,
            url: self::buildUrl($slug, (int) $category->id),
        );
    }

    private static function buildUrl(string $slug, int $id): string
    {
        $path = $slug . '-' . getModuleConfig('url.blog_category') . $id;

        return url('/' . $path);
    }
}
