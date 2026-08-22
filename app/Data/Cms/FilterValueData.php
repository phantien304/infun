<?php

namespace App\Data\Cms;

use App\Models\Entities\FilterValue;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class FilterValueData extends Data
{
    public function __construct(
        public int $id,
        public int $sort_order,
        public Collection $filter_value_descriptions,
    ) {
    }

    public static function fromModel(FilterValue $value): self
    {
        return new self(
            id: (int) $value->id,
            sort_order: (int) $value->sort_order,
            filter_value_descriptions: $value->relationLoaded('descriptions')
                ? FilterValueDescriptionData::collect($value->descriptions, Collection::class)
                : collect(),
        );
    }
}
