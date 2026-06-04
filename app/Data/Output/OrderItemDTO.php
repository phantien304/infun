<?php

namespace App\Data\Output;

use App\Models\Entities\OrdersProduct;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * 1 dòng sản phẩm trong order.
 *
 * `name` đã được CreateOrderService gắn suffix variant (vd "Áo (M / Đỏ)") —
 * blade chỉ cần hiển thị thẳng, không phải nối lại từ options.
 *
 * `productUrl` build từ description (nếu còn product). Product có thể đã bị
 * xoá / hết hiệu lực — fallback `#` để link không vỡ.
 *
 * `options` dùng `Illuminate\Support\Collection` + attribute
 * `DataCollectionOf` theo convention DTO project — KHÔNG dùng `?OrderItemOptionDTO`
 * singular (sẽ TypeError vì collect trả collection).
 */
class OrderItemDTO extends Data
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
        #[DataCollectionOf(OrderItemOptionDTO::class)]
        public Collection $options,
    ) {
    }

    public static function fromModel(OrdersProduct $product): self
    {
        $currency = (string) getConfigDb('config_currency');
        $name = (string) ($product->name ?? '');
        $price = (float) ($product->price ?? 0);
        $total = (float) ($product->total ?? 0);

        $url = '#';
        $productEntity = $product->relationLoaded('product') ? $product->product : null;
        if ($productEntity && $productEntity->relationLoaded('description') && $productEntity->description) {
            $url = buildUrl(
                resolveSlug($productEntity->description->slug ?? null, $name),
                getModuleConfig('url.product'),
                (int) $productEntity->id,
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
            priceLabel: number_format($price, 0, '', ',') . $currency,
            totalLabel: number_format($total, 0, '', ',') . $currency,
            productUrl: $url,
            options: OrderItemOptionDTO::collect($product->ordersProductOptions ?? collect()),
        );
    }
}
