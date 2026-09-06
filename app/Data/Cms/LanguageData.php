<?php

namespace App\Data\Cms;

use App\Models\Entities\Language;
use Spatie\LaravelData\Data;

class LanguageData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public ?string $name,
        public ?string $vi_name,
        public int $priority,
        public ?string $flag_icon,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Language $language): self
    {
        return new self(
            id: (int) $language->id,
            code: (string) $language->code,
            name: $language->name,
            vi_name: $language->vi_name,
            priority: (int) $language->priority,
            flag_icon: $language->flag_icon,
            deleted_at: $language->deleted_at?->toDateTimeString(),
        );
    }
}
