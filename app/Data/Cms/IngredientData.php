<?php

namespace App\Data\Cms;

use App\Models\Entities\Ingredient;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class IngredientData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $warning,
        public ?string $warning_text,
        public ?string $deleted_at,
        public Collection $effect_ids,
        public Collection $safety_ids,
        public Collection $skincare_ids,
    ) {
    }

    public static function fromModel(Ingredient $ingredient): self
    {
        return new self(
            id: (int) $ingredient->id,
            name: (string) $ingredient->name,
            description: $ingredient->description,
            warning: (int) $ingredient->warning,
            warning_text: $ingredient->warning_text,
            deleted_at: $ingredient->deleted_at?->toDateTimeString(),
            effect_ids: $ingredient->relationLoaded('ingredientEffects')
                ? $ingredient->ingredientEffects->pluck('effect_id')->values()
                : collect(),
            safety_ids: $ingredient->relationLoaded('ingredientSafeties')
                ? $ingredient->ingredientSafeties->pluck('safety_id')->values()
                : collect(),
            skincare_ids: $ingredient->relationLoaded('ingredientSkincares')
                ? $ingredient->ingredientSkincares->pluck('skincare_id')->values()
                : collect(),
        );
    }
}
