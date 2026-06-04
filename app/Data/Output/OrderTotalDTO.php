<?php

namespace App\Data\Output;

use App\Models\Entities\OrdersTotal;
use Spatie\LaravelData\Data;

/**
 * 1 dòng total trong bảng `orders_total` (sub_total, coupon, voucher,
 * reward, shipping, total). Format `value` thành `valueLabel` để blade
 * không gọi `number_format` rải rác.
 */
class OrderTotalDTO extends Data
{
    public function __construct(
        public string $code,
        public string $title,
        public float $value,
        public string $valueLabel,
        public int $sortOrder,
    ) {
    }

    public static function fromModel(OrdersTotal $total): self
    {
        $currency = (string) getConfigDb('config_currency');
        $value = (float) ($total->value ?? 0);

        return new self(
            code: (string) ($total->code ?? ''),
            title: (string) ($total->title ?? ''),
            value: $value,
            valueLabel: number_format($value, 0, '', ',') . $currency,
            sortOrder: (int) ($total->sort_order ?? 0),
        );
    }
}
