<?php

namespace App\Data\Cms;

use App\Models\Entities\OptionValue;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class OptionValueData extends Data
{
    public function __construct(
        public int $id,
        public ?string $image,
        public int $sort_order,
        public Collection $option_value_descriptions,
    ) {
    }

    public static function fromModel(OptionValue $value): self
    {
        return new self(
            id: (int) $value->id,
            image: $value->image,
            sort_order: (int) $value->sort_order,
            option_value_descriptions: $value->relationLoaded('descriptions')
                ? OptionValueDescriptionData::collect($value->descriptions, Collection::class)
                : collect(),
        );
    }
}
