<?php

namespace App\Data\Cms;

use App\Models\Entities\Effect;
use Spatie\LaravelData\Data;

class EffectData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public int $sort_order,
        public ?string $icon,
        public ?string $image_icon,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Effect $effect): self
    {
        return new self(
            id: (int) $effect->id,
            name: $effect->name,
            sort_order: (int) $effect->sort_order,
            icon: $effect->icon,
            image_icon: $effect->image_icon,
            deleted_at: $effect->deleted_at?->toDateTimeString(),
        );
    }
}
