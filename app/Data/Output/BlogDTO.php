<?php

namespace App\Data\Output;

use App\Helpers\Facades\CustomStorage;
use App\Models\Entities\Blog;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class BlogDTO extends Data
{
    public function __construct(
        public int $id,
        public int $viewed,
        public bool $featured,
        public string $title,
        public ?string $slug,
        public string $excerpt,
        public string $url,
        public ?string $image,
        public string $publishedDate,
        public string $modifiedDate,
        public ?BlogCategoryDTO $category,
        public ?UserDTO $user,
        public Lazy|string $content,
        public Lazy|string $tag,
        public Lazy|string $metaTitle,
        public Lazy|string $metaDescription,
    ) {}
    public static function fromModel(Blog $blog): self
    {
        $desc = $blog->description;
        $title       = (string) ($desc->title ?? '');
        $slug        = resolveSlug($desc->slug ?? null, $title);
        $description = (string) ($desc->description ?? '');

        return new self(
            id: (int) $blog->id,
            viewed: (int) $blog->viewed,
            featured: (bool) $blog->featured,
            title: $title,
            slug: $slug,
            excerpt: Str::limit(strip_tags($description), 160),
            url: buildUrl($slug, getModuleConfig('url.blog'), (int) $blog->id),
            image: $blog->image,
            publishedDate: $blog->created_at?->format('d/m/Y') ?? '',
            modifiedDate: $blog->updated_at?->format('d/m/Y') ?? '',
            category: $blog->blogCategory
                ? BlogCategoryDTO::fromModel($blog->blogCategory)
                : null,
            user: $blog->user
                ? UserDTO::fromModel($blog->user)
                : null,
            content: Lazy::create(fn() => (string) ($desc->content ?? '')),
            tag: Lazy::create(fn() => (string) ($desc->tag ?? '')),
            metaTitle: $desc->meta_title ?? '',
            metaDescription: $desc->meta_description ?? '',
        );
    }

    public function thumbnail(int $width, int $height, string $module = 'web'): string
    {
        return CustomStorage::getStorage('public')
            ->resizeImage($this->image, $width, $height, $module);
    }
}
