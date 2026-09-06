<?php

namespace App\Data\Cms;

use App\Models\Entities\Safety;
use Spatie\LaravelData\Data;

class SafetyData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $background,
        public int $sort_order,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Safety $safety): self
    {
        return new self(
            id: (int) $safety->id,
            name: $safety->name,
            background: $safety->background,
            sort_order: (int) $safety->sort_order,
            deleted_at: $safety->deleted_at?->toDateTimeString(),
        );
    }
}
