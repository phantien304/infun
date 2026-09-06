<?php

namespace App\Data\Cms;

use App\Models\Entities\Skincare;
use Spatie\LaravelData\Data;

class SkincareData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $icon,
        public ?string $image_icon,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Skincare $skincare): self
    {
        return new self(
            id: (int) $skincare->id,
            name: $skincare->name,
            icon: $skincare->icon,
            image_icon: $skincare->image_icon,
            deleted_at: $skincare->deleted_at?->toDateTimeString(),
        );
    }
}
