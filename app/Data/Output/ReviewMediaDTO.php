<?php

namespace App\Data\Output;

use App\Models\Entities\ReviewMedia;
use Spatie\LaravelData\Data;

class ReviewMediaDTO extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $url,
        public ?string $thumbnail,
        public ?int $width,
        public ?int $height,
        public ?int $duration,
        public int $sortOrder,
    ) {}

    public static function fromModel(ReviewMedia $m): self
    {
        return new self(
            id:        (int) $m->id,
            type:      (string) $m->type,
            url:       $m->url ? asset($m->url) : '',
            thumbnail: $m->thumbnail ? asset($m->thumbnail) : null,
            width:     $m->width,
            height:    $m->height,
            duration:  $m->duration,
            sortOrder: (int) $m->sort_order,
        );
    }
}
