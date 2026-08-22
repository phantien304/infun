<?php

namespace App\Data\Cms;

use App\Models\Entities\Option;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class OptionData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public int $role,
        public int $sort_order,
        public ?string $name,
        public ?string $name_display,
        public ?string $deleted_at,
        public Collection $option_descriptions,
        public Collection $option_values,
    ) {
    }

    public static function fromModel(Option $option): self
    {
        $description = $option->relationLoaded('descriptions')
            ? $option->descriptions->first()
            : null;

        return new self(
            id: (int) $option->id,
            type: (string) $option->type,
            role: $option->role?->value ?? 0,
            sort_order: (int) $option->sort_order,
            name: $option->name ?? $description?->name,
            name_display: $option->name_display ?? $description?->name_display,
            deleted_at: $option->deleted_at?->toDateTimeString(),
            option_descriptions: $option->relationLoaded('descriptions')
                ? OptionDescriptionData::collect($option->descriptions, Collection::class)
                : collect(),
            option_values: $option->relationLoaded('optionValues')
                ? OptionValueData::collect($option->optionValues, Collection::class)
                : collect(),
        );
    }
}
