<?php

namespace App\Data\Cms;

use App\Models\Entities\Blog;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class BlogData extends Data
{
    public function __construct(
        public int $id,
        public ?int $category_id,
        public ?int $author_id,
        public ?string $image,
        public int $viewed,
        public int $featured,
        public ?string $title,
        public ?string $author_name,
        public ?string $deleted_at,
        public Collection $blog_descriptions,
    ) {
    }

    public static function fromModel(Blog $blog): self
    {
        $title = $blog->title
            ?? ($blog->relationLoaded('descriptions')
                ? $blog->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $blog->id,
            category_id: $blog->category_id !== null ? (int) $blog->category_id : null,
            author_id: $blog->author_id !== null ? (int) $blog->author_id : null,
            image: $blog->image,
            viewed: (int) $blog->viewed,
            featured: (int) $blog->featured,
            title: $title,
            // Denormalized cho form.jsx hiển thị tên tác giả ngay khi load edit
            // (dropdown Author là remote-search theo keyword, không "load all"
            // nên cần tên có sẵn để show label đúng mà không phải search lại).
            author_name: $blog->relationLoaded('user') ? $blog->user?->full_name : null,
            deleted_at: $blog->deleted_at?->toDateTimeString(),
            blog_descriptions: $blog->relationLoaded('descriptions')
                ? BlogDescriptionData::collect($blog->descriptions, Collection::class)
                : collect(),
        );
    }
}
