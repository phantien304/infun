<?php

namespace App\Data\Cms;

use App\Models\Entities\Filter;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class FilterData extends Data
{
    public function __construct(
        public int $id,
        public int $sort_order,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $filter_descriptions,
        public Collection $filter_values,
    ) {
    }

    public static function fromModel(Filter $filter): self
    {
        $name = $filter->name
            ?? ($filter->relationLoaded('descriptions')
                ? $filter->descriptions->first()?->name
                : null);

        return new self(
            id: (int) $filter->id,
            sort_order: (int) $filter->sort_order,
            name: $name,
            deleted_at: $filter->deleted_at?->toDateTimeString(),
            filter_descriptions: $filter->relationLoaded('descriptions')
                ? FilterDescriptionData::collect($filter->descriptions, Collection::class)
                : collect(),
            filter_values: $filter->relationLoaded('filterValues')
                ? FilterValueData::collect($filter->filterValues, Collection::class)
                : collect(),
        );
    }
}
