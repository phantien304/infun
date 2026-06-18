<?php

namespace App\Data\Output;

use App\Models\Entities\UserWishlist;
use Spatie\LaravelData\Data;

class WishlistItemDTO extends Data
{
    public function __construct(
        public int $productId,
        public string $name,
        public string $model,
        public ?string $image,
        public string $url,
        public string $priceLabel,
        public float $effectivePrice,
        public string $stockLabel,
        public bool $inStock,
    ) {
    }

    public static function fromModel(UserWishlist $wishlist): self
    {
        $product = $wishlist->product;
        $desc = $product?->description;
        $name = (string) ($desc->name ?? '');
        $slug = resolveSlug($desc->slug ?? null, $name);

        $effective = (float) ($product?->price ?? 0);
        $variantSpecial = $product?->defaultVariant?->productVariantSpecial;
        if ($variantSpecial) {
            $effective = (float) $variantSpecial->price;
        }

        $currency = (string) getConfigDb('config_currency');
        $stock = (int) ($product->quantity ?? 0);
        $inStock = $stock > 0;
        $stockLabel = $inStock
            ? (getConfigDb('config_stock_display')
                ? (string) $stock
                : (string) getModuleConfig('product.text_instock'))
            : (string) ($product?->stockStatus?->name
                ?? getModuleConfig('product.text_outstock'));

        return new self(
            productId: (int) $wishlist->product_id,
            name: $name,
            model: (string) ($product->model ?? ''),
            image: $product->image ?? null,
            url: $product ? buildUrl($slug, getModuleConfig('url.product'), (int) $product->id) : '#',
            priceLabel: $effective > 0
                ? number_format($effective, 0, '', ',') . $currency
                : (string) getModuleConfig('product.text_contact'),
            effectivePrice: $effective,
            stockLabel: $stockLabel,
            inStock: $inStock,
        );
    }
}
