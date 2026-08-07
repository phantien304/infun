<?php

namespace App\Data\Cms;

use App\Models\Entities\StoreReviewDescription;
use Spatie\LaravelData\Data;

class StoreReviewDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $title,
        public ?string $content,
        public ?string $meta_title,
        public ?string $meta_description,
    ) {
    }

    public static function fromModel(StoreReviewDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            title: $d->title,
            content: $d->content,
            meta_title: $d->meta_title,
            meta_description: $d->meta_description,
        );
    }
}
