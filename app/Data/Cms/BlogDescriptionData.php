<?php

namespace App\Data\Cms;

use App\Models\Entities\BlogDescription;
use Spatie\LaravelData\Data;

class BlogDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $title,
        public ?string $description,
        public ?string $content,
        public ?string $tag,
        public ?string $meta_title,
        public ?string $meta_description,
    ) {
    }

    public static function fromModel(BlogDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            title: $d->title,
            description: $d->description,
            content: $d->content,
            tag: $d->tag,
            meta_title: $d->meta_title,
            meta_description: $d->meta_description,
        );
    }
}
