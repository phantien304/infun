<?php

namespace App\Data\Cms;

use App\Models\Entities\AffiliateCommissionRule;
use Spatie\LaravelData\Data;

/**
 * Rate hoa hồng theo ngành hàng. Một ngành hàng đúng một rule (UNIQUE
 * category_id ở DB).
 *
 * Rate này chỉ áp khi KOL KHÔNG có `commission_rate` riêng — thứ tự ưu tiên
 * đầy đủ nằm ở AffiliateConversionService, không phải ở màn này.
 */
class AffiliateCommissionRuleData extends Data
{
    public function __construct(
        public int $id,
        public int $category_id,
        public ?string $category_title,
        public float $rate,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(AffiliateCommissionRule $rule): self
    {
        $category = $rule->relationLoaded('category') ? $rule->category : null;

        return new self(
            id: (int) $rule->id,
            category_id: (int) $rule->category_id,
            category_title: ($category && $category->relationLoaded('description'))
                ? $category->description?->title
                : null,
            rate: (float) $rule->rate,
            created_at: $rule->created_at?->toDateTimeString(),
        );
    }
}
