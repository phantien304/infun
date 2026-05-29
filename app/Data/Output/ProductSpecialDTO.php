<?php

namespace App\Data\Output;

use App\Models\Entities\ProductSpecial;
use Spatie\LaravelData\Data;

class ProductSpecialDTO extends Data
{
    public function __construct(
        public int $id,
        public ?int $userGroupId,
        public int $priority,
        public float $priceRegular,
        public float $pricePromotion,
        public string $priceRegularLabel,
        public string $pricePromotionLabel,
        public ?int $discountPercent,
        public ?string $dateStart,
        public ?string $dateEnd
    ) {}

    public static function fromModel(ProductSpecial $productSpecial, float $priceRegular = 0): self
    {
        $pricePromotion = (float) $productSpecial->price;

        return new self(
            id: (int) $productSpecial->id,
            userGroupId: $productSpecial->user_group_id,
            priority: (int) $productSpecial->priority,
            priceRegular: $priceRegular,
            pricePromotion: $pricePromotion,
            priceRegularLabel: self::formatPrice($priceRegular),
            pricePromotionLabel: self::formatPrice($pricePromotion),
            discountPercent: self::discountPercent($priceRegular, $pricePromotion),
            dateStart: $productSpecial->date_start?->format('Y-m-d H:i:s'),
            dateEnd: $productSpecial->date_end?->format('Y-m-d H:i:s')
        );
    }

    private static function formatPrice(float $price): string
    {
        return $price > 0
            ? number_format($price) . getConfigDb('config_currency')
            : getModuleConfig('product.text_contact');
    }

    private static function discountPercent(float $regular, float $promotion): ?int
    {
        if ($regular <= 0 || $promotion <= 0 || $promotion >= $regular) {
            return null;
        }
        return (int) round(($regular - $promotion) / $regular * 100);
    }
}
