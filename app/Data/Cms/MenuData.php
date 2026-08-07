<?php

namespace App\Data\Cms;

use App\Models\Entities\Menu;
use Spatie\LaravelData\Data;

class MenuData extends Data
{
    public function __construct(
        public int $id,
        public ?string $title,
        public ?string $position,
        public ?string $theme,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Menu $menu): self
    {
        return new self(
            id: (int) $menu->id,
            title: $menu->title,
            position: $menu->position,
            theme: $menu->theme,
            deleted_at: $menu->deleted_at?->toDateTimeString(),
        );
    }
}
