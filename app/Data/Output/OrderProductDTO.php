<?php

namespace App\Data\Output;

use App\Models\Entities\OrdersProduct;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class OrderProductDTO extends Data
{
    public function __construct(
        public int $id,
        public int $orderId,
        public int $productId,
        public string $name,
        public string $model,
        public int $quantity,
        public float $price,
        public float $total,
        public string $priceLabel,
        public string $totalLabel,
        public string $productUrl,
        public Collection $options,
    ) {
    }

    public static function fromModel(OrdersProduct $product, ?string $currencyCode = null, float $currencyValue = 1.0): self
    {
        $name = (string) ($product->name ?? '');
        $price = (float) ($product->price ?? 0);
        $total = (float) ($product->total ?? 0);

        $url = '#';
        $product = $product->relationLoaded('product') ? $product->product : null;
        if ($product && $product->relationLoaded('description') && $product->description) {
            $url = buildUrl(
                resolveSlug($product->description->slug ?? null, $name),
                getModuleConfig('url.product'),
                (int) $product->id,
            );
        }

        return new self(
            id: (int) ($product->id ?? 0),
            orderId: (int) ($product->order_id ?? 0),
            productId: (int) ($product->product_id ?? 0),
            name: $name,
            model: (string) ($product->model ?? ''),
            quantity: (int) ($product->quantity ?? 0),
            price: $price,
            total: $total,
            priceLabel: moneyAtBuy($price, $currencyCode, $currencyValue),
            totalLabel: moneyAtBuy($total, $currencyCode, $currencyValue),
            productUrl: $url,
            options: OrderProductOptionDTO::collect($product->ordersProductOptions ?? collect()),
        );
    }
}
