<?php

namespace App\Data\Cms;

use App\Models\Entities\VoucherThemeDescription;
use Spatie\LaravelData\Data;

class VoucherThemeDescriptionData extends Data
{
    public function __construct(
        public string $language_code,
        public ?string $name,
    ) {
    }

    public static function fromModel(VoucherThemeDescription $description): self
    {
        return new self(
            language_code: (string) $description->language_code,
            name: $description->name,
        );
    }
}
