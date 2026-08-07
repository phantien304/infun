<?php

namespace App\Data\Cms;

use App\Models\Entities\BannerValueDescription;
use Spatie\LaravelData\Data;

class BannerValueDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $title,
        public ?string $content,
    ) {
    }

    public static function fromModel(BannerValueDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            title: $d->title,
            content: $d->content,
        );
    }
}
