<?php

namespace App\Data\Output;

use App\Data\Concerns\HasThumbnail;
use App\Data\Concerns\LazyData;
use App\Models\Entities\Blog;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class BlogDTO extends Data
{
    use HasThumbnail, LazyData;
    public function __construct(
        public int $id,
        public int $viewed,
        public bool $featured,
        public string $title,
        public ?string $slug,
        public ?string $description,
        public ?string $excerpt,
        public string $url,
        public ?string $image,
        public string $publishedDate,
        public ?string $modifiedDate,
        public string $diffForHumans,
        public ?BlogCategoryDTO $category,
        public ?UserDTO $user,
        public Lazy|string $content,
        public Lazy|string $tag,
        public string $metaTitle,
        public string $metaDescription,
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
            description: $description,
            excerpt: Str::limit(strip_tags($description), 160),
            url: buildUrl($slug, getModuleConfig('url.blog'), (int) $blog->id),
            image: $blog->image,
            publishedDate: $blog->created_at?->format('d/m/Y') ?? '',
            modifiedDate: $blog->updated_at?->format('d/m/Y') ?? '',
            diffForHumans: $blog->created_at?->diffForHumans() ?? '',
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
    public function content(): string
    {
        return $this->resolveLazy($this->content);
    }
    public function tag(): string
    {
        return $this->resolveLazy($this->tag);
    }
}
