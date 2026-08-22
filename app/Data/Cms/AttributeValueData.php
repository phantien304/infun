<?php

namespace App\Data\Cms;

use App\Models\Entities\AttributeValue;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class AttributeValueData extends Data
{
    public function __construct(
        public int $id,
        public int $sort_order,
        public Collection $attribute_value_descriptions,
    ) {
    }

    public static function fromModel(AttributeValue $value): self
    {
        return new self(
            id: (int) $value->id,
            sort_order: (int) $value->sort_order,
            attribute_value_descriptions: $value->relationLoaded('descriptions')
                ? AttributeValueDescriptionData::collect($value->descriptions, Collection::class)
                : collect(),
        );
    }
}
