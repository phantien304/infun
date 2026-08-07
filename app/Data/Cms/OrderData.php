<?php

namespace App\Data\Cms;

use App\Models\Entities\Orders;
use Spatie\LaravelData\Data;

/**
 * Chi tiết đầy đủ 1 order cho order/form.jsx + order/view.jsx. Nested
 * (orders_products/orders_totals/orders_histories) để mảng thuần (array),
 * KHÔNG dựng thêm Data class con riêng — shape đơn giản, đọc 1 chiều, giống
 * cách ProductData xử lý product_categories/product_images/....
 */
class OrderData extends Data
{
    public function __construct(
        public int $id,
        public string $invoice_no,
        public string $invoice_prefix,
        public ?int $user_id,
        public ?int $user_address_id,
        public ?string $user_full_name,
        public ?string $user_email,
        public string $full_name,
        public string $email,
        public string $telephone,
        public string $address,
        public ?string $country,
        public ?int $country_id,
        public ?string $zone,
        public ?int $zone_id,
        public ?string $district,
        public ?int $district_id,
        public ?string $ward,
        public ?int $ward_id,
        public ?string $payment_code,
        public ?string $payment_name,
        public ?string $carrier_code,
        public ?string $carrier_name,
        public ?string $comment,
        public float $total,
        public int $order_status_id,
        public ?string $order_status_name,
        public string $currency_code,
        public ?string $created_at,
        public ?string $updated_at,
        public ?string $deleted_at,
        public array $orders_products,
        public array $orders_totals,
        public array $orders_histories,
    ) {
    }

    public static function fromModel(Orders $order): self
    {
        return new self(
            id: (int) $order->id,
            invoice_no: (string) ($order->invoice_no ?? ''),
            invoice_prefix: (string) ($order->invoice_prefix ?? ''),
            user_id: $order->user_id !== null ? (int) $order->user_id : null,
            user_address_id: $order->user_address_id !== null ? (int) $order->user_address_id : null,
            user_full_name: $order->relationLoaded('user') ? $order->user?->full_name : null,
            user_email: $order->relationLoaded('user') ? $order->user?->email : null,
            full_name: (string) ($order->full_name ?? ''),
            email: (string) ($order->email ?? ''),
            telephone: (string) ($order->telephone ?? ''),
            address: (string) ($order->address ?? ''),
            country: $order->country,
            country_id: $order->country_id !== null ? (int) $order->country_id : null,
            zone: $order->zone,
            zone_id: $order->zone_id !== null ? (int) $order->zone_id : null,
            district: $order->district,
            district_id: $order->district_id !== null ? (int) $order->district_id : null,
            ward: $order->ward,
            ward_id: $order->ward_id !== null ? (int) $order->ward_id : null,
            payment_code: $order->payment_code,
            payment_name: $order->relationLoaded('payment') ? $order->payment?->description?->name : null,
            carrier_code: $order->carrier_code,
            carrier_name: $order->relationLoaded('carrier') ? $order->carrier?->name : null,
            comment: $order->comment,
            total: (float) ($order->total ?? 0),
            order_status_id: (int) ($order->order_status_id ?? 0),
            order_status_name: $order->relationLoaded('ordersStatus') ? $order->ordersStatus?->name : null,
            currency_code: (string) ($order->currency_code ?: 'VND'),
            created_at: $order->created_at?->toDateTimeString(),
            updated_at: $order->updated_at?->toDateTimeString(),
            deleted_at: $order->deleted_at?->toDateTimeString(),
            orders_products: $order->relationLoaded('ordersProducts')
                ? $order->ordersProducts->map(fn ($p) => [
                    'id'                 => $p->id,
                    'product_id'         => $p->product_id,
                    'product_variant_id' => $p->product_variant_id,
                    'name'               => $p->name,
                    'model'              => $p->model,
                    'quantity'           => (int) $p->quantity,
                    'price'              => (float) $p->price,
                    'total'              => (float) $p->total,
                    'options'            => ($p->ordersProductOptions ?? collect())->map(fn ($o) => [
                        'product_option_id'       => $o->product_option_id,
                        'product_option_value_id' => $o->product_option_value_id,
                        'name'                     => $o->name,
                        'value'                    => $o->value,
                        'type'                     => $o->type,
                        'required'                 => $o->required,
                        'children'                 => self::safeUnserialize($o->children),
                    ])->values()->all(),
                ])->values()->all()
                : [],
            orders_totals: $order->relationLoaded('ordersTotals')
                ? $order->ordersTotals->map(fn ($t) => [
                    'code'  => $t->code,
                    'title' => $t->title,
                    'value' => (float) $t->value,
                ])->values()->all()
                : [],
            orders_histories: $order->relationLoaded('ordersHistories')
                ? $order->ordersHistories->map(fn ($h) => [
                    'id'                => $h->id,
                    'order_status_id'   => $h->order_status_id,
                    'order_status_name' => $h->ordersStatus?->name,
                    'user_email'        => $h->user?->email,
                    'comment'           => $h->comment,
                    'notify'            => (bool) $h->notify,
                    'created_at'        => $h->created_at?->toDateTimeString(),
                ])->values()->all()
                : [],
        );
    }

    /** children là text serialize() từ mt219/CartService — không tin trực tiếp. */
    private static function safeUnserialize(?string $raw): array
    {
        if (! $raw) {
            return [];
        }
        $value = @unserialize($raw, ['allowed_classes' => false]);

        return is_array($value) ? $value : [];
    }
}
