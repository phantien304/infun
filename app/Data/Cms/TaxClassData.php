<?php

namespace App\Data\Cms;

use App\Models\Entities\TaxClass;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class TaxClassData extends Data
{
    public function __construct(
        public int $id,
        public ?string $title,
        public ?string $description,
        public ?string $deleted_at,
        public Collection $tax_rules,
    ) {
    }

    public static function fromModel(TaxClass $taxClass): self
    {
        return new self(
            id: (int) $taxClass->id,
            title: $taxClass->title,
            description: $taxClass->description,
            deleted_at: $taxClass->deleted_at?->toDateTimeString(),
            tax_rules: $taxClass->relationLoaded('taxRules')
                ? TaxRuleData::collect($taxClass->taxRules, Collection::class)
                : collect(),
        );
    }
}
