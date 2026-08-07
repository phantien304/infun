<?php

namespace App\Data\Cms;

use App\Models\Entities\Orders;
use Spatie\LaravelData\Data;

class OrderListData extends Data
{
    public function __construct(
        public int $id,
        public string $invoice_no,
        public string $full_name,
        public int $order_status_id,
        public ?string $order_status_name,
        public float $total,
        public string $currency_code,
        public ?string $created_at,
        public ?string $updated_at,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Orders $order): self
    {
        return new self(
            id: (int) $order->id,
            invoice_no: (string) ($order->invoice_no ?? ''),
            full_name: (string) ($order->full_name ?? ''),
            order_status_id: (int) ($order->order_status_id ?? 0),
            order_status_name: $order->relationLoaded('ordersStatus') ? $order->ordersStatus?->name : null,
            total: (float) ($order->total ?? 0),
            currency_code: (string) ($order->currency_code ?: 'VND'),
            created_at: $order->created_at?->toDateTimeString(),
            updated_at: $order->updated_at?->toDateTimeString(),
            deleted_at: $order->deleted_at?->toDateTimeString(),
        );
    }
}
