<?php

namespace App\Data\Cms;

use App\Models\Entities\MenuValueDescription;
use Spatie\LaravelData\Data;

class MenuValueDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $title,
        public ?string $link,
    ) {
    }

    public static function fromModel(MenuValueDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            title: $d->title,
            link: $d->link,
        );
    }
}
