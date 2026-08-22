<?php

namespace App\Data\Cms;

use App\Models\Entities\Attribute;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class AttributeData extends Data
{
    public function __construct(
        public int $id,
        public int $sort_order,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $attribute_descriptions,
        public Collection $attribute_values,
    ) {
    }

    public static function fromModel(Attribute $attribute): self
    {
        $name = $attribute->name
            ?? ($attribute->relationLoaded('descriptions')
                ? $attribute->descriptions->first()?->name
                : null);

        return new self(
            id: (int) $attribute->id,
            sort_order: (int) $attribute->sort_order,
            name: $name,
            deleted_at: $attribute->deleted_at?->toDateTimeString(),
            attribute_descriptions: $attribute->relationLoaded('descriptions')
                ? AttributeDescriptionData::collect($attribute->descriptions, Collection::class)
                : collect(),
            attribute_values: $attribute->relationLoaded('attributeValues')
                ? AttributeValueData::collect($attribute->attributeValues, Collection::class)
                : collect(),
        );
    }
}
