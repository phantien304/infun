<?php

namespace App\Data\Output;

use App\Models\Entities\FilterValue;
use Spatie\LaravelData\Data;

class FilterValueDTO extends Data
{
    public function __construct(
        public int $id,
        public int $filterId,
        public int $sortOrder,
        public ?string $name
    ) {
    }

    public static function fromModel(FilterValue $filterValue): self
    {
        $desc = $filterValue->description;
        $name = (string) ($desc->name ?? '');

        return new self(
            id: (int) $filterValue->id,
            filterId: (int) $filterValue->filter_id,
            sortOrder: (int) $filterValue->sort_order,
            name: $name
        );
    }
}
