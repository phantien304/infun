<?php

namespace App\Data\Cms;

use App\Models\Entities\BlogCategoryDescription;
use Spatie\LaravelData\Data;

class BlogCategoryDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $title,
        public ?string $description,
        public ?string $slug,
        public ?string $meta_title,
        public ?string $meta_description,
    ) {
    }

    public static function fromModel(BlogCategoryDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            title: $d->title,
            description: $d->description,
            slug: $d->slug,
            meta_title: $d->meta_title,
            meta_description: $d->meta_description,
        );
    }
}
