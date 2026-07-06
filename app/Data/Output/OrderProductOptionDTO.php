<?php

namespace App\Data\Output;

use App\Models\Entities\OrdersProductOption;
use Spatie\LaravelData\Data;

/**
 * 1 option của 1 order item (1 cặp `option_id → option_value_id` user đã
 * chọn, hoặc 1 custom field user điền lúc checkout).
 *
 * `variation` legacy:
 *  - 1 = option 2-level cũ (children serialized) — KHÔNG còn dùng cho dữ
 *    liệu mới sau cluster variant. Giữ field để render order history cũ.
 *  - 2 = option 1-level (flat) — schema mới luôn 2.
 *
 * `childrenLabel` đã pre-format từ unserialize(children) — tránh blade gọi
 * unserialize trực tiếp (dữ liệu legacy có thể lỗi serialize → blade crash).
 */
class OrderProductOptionDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $value,
        public int $variation,
        public string $childrenLabel,
    ) {
    }

    public static function fromModel(OrdersProductOption $option): self
    {
        $variation = (int) ($option->variation ?? 2);
        $childrenLabel = '';
        if ($variation === 1 && filled($option->children)) {
            $childrenLabel = self::extractChildrenLabel((string) $option->children);
        }

        return new self(
            id: (int) ($option->id ?? 0),
            name: (string) ($option->name ?? ''),
            value: (string) ($option->value ?? ''),
            variation: $variation,
            childrenLabel: $childrenLabel,
        );
    }

    /**
     * `children` cũ là PHP serialize của `[['name'=>..., 'value'=>...], ...]`.
     * Unserialize trong try/catch để dữ liệu lỗi không phá page.
     */
    private static function extractChildrenLabel(string $raw): string
    {
        try {
            $children = @unserialize($raw, ['allowed_classes' => false]);
        } catch (\Throwable) {
            return '';
        }
        if (! is_array($children) || empty($children)) {
            return '';
        }
        $name = '';
        $values = [];
        foreach ($children as $child) {
            $name = (string) ($child['name'] ?? $name);
            $values[] = (string) ($child['value'] ?? '');
        }

        return $name === ''
            ? implode(', ', array_filter($values))
            : $name . ': ' . implode(', ', array_filter($values));
    }
}
