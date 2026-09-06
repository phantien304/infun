<?php

namespace App\Data\Cms;

use App\Models\Entities\TaxRule;
use Spatie\LaravelData\Data;

class TaxRuleData extends Data
{
    public function __construct(
        public int $id,
        public ?int $tax_rate_id,
        public ?string $based,
        public ?int $priority,
    ) {
    }

    public static function fromModel(TaxRule $rule): self
    {
        return new self(
            id: (int) $rule->id,
            tax_rate_id: $rule->tax_rate_id !== null ? (int) $rule->tax_rate_id : null,
            based: $rule->based,
            priority: $rule->priority !== null ? (int) $rule->priority : null,
        );
    }
}
