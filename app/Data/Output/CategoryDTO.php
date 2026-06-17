<?php

namespace App\Data\Output;

use App\Data\Concerns\HasThumbnail;
use App\Data\Concerns\LazyData;
use App\Models\Entities\Category;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class CategoryDTO extends Data
{
    use HasThumbnail;
    use LazyData;

    public function __construct(
        public int $id,
        public int $parent_id,
        public int $sort_order,
        public ?string $image,
        public ?string $icon,
        public string $title,
        public string $description,
        public Lazy|string $content,
        public string $slug,
        public string $url,
        public Lazy|string $metaTitle,
        public Lazy|string $metaDescription,
    ) {
    }

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
            icon: $category->icon,
            title: $title,
            description: (string) ($desc->description ?? ''),
            content: Lazy::create(fn () => (string) ($desc->content ?? '')),
            slug: $slug,
            url: buildUrl($slug, getModuleConfig('url.category'), (int) $category->id),
            metaTitle: Lazy::create(fn () => (string) ($desc->meta_title ?? '')),
            metaDescription: Lazy::create(fn () => (string) ($desc->meta_description ?? '')),
        );
    }

    public function content(): string
    {
        return $this->resolveLazy($this->content);
    }

    public function metaTitle(): string
    {
        return $this->resolveLazy($this->metaTitle);
    }

    public function metaDescription(): string
    {
        return $this->resolveLazy($this->metaDescription);
    }
}
