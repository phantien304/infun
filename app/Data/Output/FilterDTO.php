<?php

namespace App\Data\Output;

use App\Models\Entities\Filter;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class FilterDTO extends Data
{
    public function __construct(
        public int $id,
        public int $sortOrder,
        public ?string $name,
        public Collection $filterValues,
    ) {
    }

    public static function fromModel(Filter $filter): self
    {
        $desc = $filter->description;
        $name = (string) ($desc->name ?? '');

        return new self(
            id: (int) $filter->id,
            sortOrder: (int) $filter->sort_order,
            name: $name,
            filterValues: FilterValueDTO::collect($filter->filterValues ?? collect()),
        );
    }
}
