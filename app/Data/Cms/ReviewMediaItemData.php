<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewMedia;
use Spatie\LaravelData\Data;

class ReviewMediaItemData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $url,
        public ?string $thumbnail,
        public ?int $width,
        public ?int $height,
        public ?int $duration,
        public int $sort_order,
        public bool $is_active,
    ) {
    }

    public static function fromModel(ReviewMedia $m): self
    {
        return new self(
            id: (int) $m->id,
            type: (string) $m->type,
            url: $m->url ? asset($m->url) : '',
            thumbnail: $m->thumbnail ? asset($m->thumbnail) : null,
            width: $m->width,
            height: $m->height,
            duration: $m->duration,
            sort_order: (int) $m->sort_order,
            is_active: (bool) $m->is_active,
        );
    }
}
