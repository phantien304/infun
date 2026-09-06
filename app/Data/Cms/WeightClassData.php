<?php

namespace App\Data\Cms;

use App\Models\Entities\WeightClass;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class WeightClassData extends Data
{
    public function __construct(
        public int $id,
        public float $value,
        public ?string $title,
        public ?string $unit,
        public ?string $deleted_at,
        public Collection $weight_class_descriptions,
    ) {
    }

    public static function fromModel(WeightClass $weightClass): self
    {
        // title/unit: ưu tiên attribute phẳng từ join của listForCms(); nhánh
        // show/update (relation descriptions đã load, không join) fallback
        // sang bản ghi đầu của descriptions — mirror CategoryData::fromModel.
        $description = $weightClass->relationLoaded('descriptions')
            ? $weightClass->descriptions->first()
            : null;

        return new self(
            id: (int) $weightClass->id,
            value: (float) $weightClass->value,
            title: $weightClass->title ?? $description?->title,
            unit: $weightClass->unit ?? $description?->unit,
            deleted_at: $weightClass->deleted_at?->toDateTimeString(),
            weight_class_descriptions: $weightClass->relationLoaded('descriptions')
                ? WeightClassDescriptionData::collect($weightClass->descriptions, Collection::class)
                : collect(),
        );
    }
}
