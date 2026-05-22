<?php

namespace App\Data\Output;

use App\Models\Entities\BlogTag;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class BlogTagDTO extends Data
{
    public function __construct(
        public int $id,
        public int $background,
        public string $title,
        public string $excerpt,
        public string $url,
        public string $publishedDate,
        public string $modifiedDate,
        public Lazy|string $content,
        public ?string $metaTitle,
        public ?string $metaDescription,
    ) {}
    public static function fromModel(BlogTag $blogTag): self
    {
        $desc = $blogTag->description;
        $title       = (string) ($desc->title ?? '');
        $slug        = resolveSlug($desc->slug ?? null, $title);
        $description = (string) ($desc->description ?? '');

        return new self(
            id: (int) $blogTag->id,
            title: $title,
            background: $blogTag->background,
            excerpt: Str::limit(strip_tags($description), 160),
            url: buildUrl($slug, getModuleConfig('url.blog'), (int) $blogTag->id),
            publishedDate: $blogTag->created_at?->format('d/m/Y') ?? '',
            modifiedDate: $blogTag->updated_at?->format('d/m/Y') ?? '',
            content: Lazy::create(fn() => (string) ($desc->content ?? '')),
            metaTitle: $desc->meta_title ?? '',
            metaDescription: $desc->meta_description ?? '',
        );
    }
}
