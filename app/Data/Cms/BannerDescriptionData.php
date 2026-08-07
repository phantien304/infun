<?php

namespace App\Data\Cms;

use App\Models\Entities\BannerDescription;
use Spatie\LaravelData\Data;

class BannerDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $title,
    ) {
    }

    public static function fromModel(BannerDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            title: $d->title,
        );
    }
}
