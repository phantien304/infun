<?php

namespace App\Data\Output;

use App\Models\Entities\ReviewCriteria;
use Spatie\LaravelData\Data;

class ReviewCriteriaDTO extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $hint,
        public ?string $icon,
        public int $sortOrder,
        public bool $isRequired,
        public bool $isActive,
    ) {}

    public static function fromModel(ReviewCriteria $m): self
    {
        $desc = $m->description;
        return new self(
            id:         (int) $m->id,
            code:       (string) $m->code,
            name:       (string) ($desc->name ?? $m->code),
            hint:       $desc->hint ?? null,
            icon:       $m->icon,
            sortOrder:  (int) $m->sort_order,
            isRequired: (bool) $m->is_required,
            isActive:   (bool) $m->is_active,
        );
    }
}
