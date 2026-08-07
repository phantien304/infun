<?php

namespace App\Data\Cms;

use App\Models\Entities\UserGroupDescription;
use Spatie\LaravelData\Data;

class UserGroupDescriptionData extends Data
{
    public function __construct(
        public ?string $language_code,
        public ?string $name,
        public ?string $description,
    ) {
    }

    public static function fromModel(UserGroupDescription $d): self
    {
        return new self(
            language_code: $d->language_code,
            name: $d->name,
            description: $d->description,
        );
    }
}
