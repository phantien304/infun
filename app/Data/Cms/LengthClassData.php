<?php

namespace App\Data\Cms;

use App\Models\Entities\LengthClass;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class LengthClassData extends Data
{
    public function __construct(
        public int $id,
        public float $value,
        public ?string $title,
        public ?string $unit,
        public ?string $deleted_at,
        public Collection $length_class_descriptions,
    ) {
    }

    public static function fromModel(LengthClass $lengthClass): self
    {
        // title/unit: ưu tiên attribute phẳng từ join của listForCms(); nhánh
        // show/update (relation descriptions đã load, không join) fallback
        // sang bản ghi đầu của descriptions — mirror CategoryData::fromModel.
        $description = $lengthClass->relationLoaded('descriptions')
            ? $lengthClass->descriptions->first()
            : null;

        return new self(
            id: (int) $lengthClass->id,
            value: (float) $lengthClass->value,
            title: $lengthClass->title ?? $description?->title,
            unit: $lengthClass->unit ?? $description?->unit,
            deleted_at: $lengthClass->deleted_at?->toDateTimeString(),
            length_class_descriptions: $lengthClass->relationLoaded('descriptions')
                ? LengthClassDescriptionData::collect($lengthClass->descriptions, Collection::class)
                : collect(),
        );
    }
}
