<?php

namespace App\Services;

use App\Models\Entities\Option;
use App\Models\Entities\ProductOption;
use Illuminate\Support\Collection;

/**
 * Chuẩn hoá ProductOption tree → mảng phẳng dùng cho blade detail.
 *
 * Logic gốc nằm trong ProductController cũ (`_buildProductOption`,
 * `_processProductOptionValues`, `_processProductOptionValues2`).
 * Tách ra service vì:
 *  - controller giữ "slim" theo chuẩn dự án mới
 *  - logic stateless, tái dùng được (vd: api detail trả JSON)
 */
class ProductOptionService
{
    /**
     * @param  Collection<int,ProductOption>  $productOptions  đã eager-load đủ
     *                                                         option.optionValues.description + productOptionValues.optionValue.description +
     *                                                         productOptionValues.productOptionValues2.optionValue.description
     * @return array{0: array, 1: array}  [options, imageOptions]
     */
    public function build(Collection $productOptions): array
    {
        $data = [];
        $images = [];

        foreach ($productOptions as $index => $item) {
            $optionId = $item->option?->id;
            $variation = $this->resolveVariation($optionId);
            $children = $this->buildChildren($item->id);

            [$productOptionValues, $imageOption] = $this->buildOptionValues(
                $item->productOptionValues ?? collect(),
                $variation,
                $item->option?->type,
                $children,
            );

            $images = array_merge($images, $imageOption);

            $data[$index] = [
                'id' => $item->id,
                'value' => $item->value,
                'required' => $item->required,
                'option_id' => $optionId,
                'option_value_id' => [],
                'option_value_2_id' => [],
                'option_name' => $item->option?->description?->name,
                'option_type' => $item->option?->type,
                'name_display' => $item->option?->description?->name_display,
                'variation' => $variation,
                'product_option_values' => $productOptionValues,
                'children' => $children,
            ];
        }

        return [$data, $images];
    }

    /**
     * `variation` ở DB: 1 = chọn (radio/select), 2 = nhập. Convention legacy.
     */
    protected function resolveVariation(?int $optionId): int
    {
        if (! $optionId) {
            return 2;
        }
        return Option::where('id', $optionId)->where('variation', 1)->exists() ? 1 : 2;
    }

    protected function buildChildren(int $productOptionId): ?array
    {
        $child = ProductOption::query()
            ->where('parent', $productOptionId)
            ->with([
                'option.description',
                'option.optionValues' => fn ($q) => $q->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC'),
                'option.optionValues.description',
            ])
            ->first();

        if (! $child || ! $child->option) {
            return null;
        }

        $optChildValues = $child->option->optionValues->map(fn ($ov) => [
            'id' => $ov->id,
            'value' => $ov->description?->name,
            'image' => resizeImage($ov->image, 50, 50, 'client'),
        ])->all();

        return [
            'id' => $child->option->id,
            'name' => $child->option->description?->name,
            'name_display' => $child->option->description?->name_display,
            'type' => $child->option->type,
            'variation' => $child->option->variation,
            'option' => $optChildValues,
        ];
    }

    protected function buildOptionValues(
        Collection $productOptionValues,
        int $variation,
        ?string $optionType,
        ?array $children,
    ): array {
        $data = [];
        $images = [];
        $lastVariation = array_key_last(getCoreConfig('variation'));

        foreach ($productOptionValues as $index => $optValue) {
            if ($optionType === 'image') {
                $images[] = [
                    'key' => 'opt'.$optValue->id,
                    'image' => $optValue->image,
                ];
            }

            $row = [
                'id' => $optValue->id,
                'image' => $optValue->image ? resizeImage($optValue->image, 1000, 1000, 'client') : '',
                'option_value_1_id' => $optValue->option_value_1_id,
                'name' => $optValue->optionValue?->description?->name ?? '',
                'product_id' => $optValue->product_id,
                'product_option_id' => $optValue->product_option_id,
                'product_option_values2' => $this->buildOptionValues2(
                    $optValue->productOptionValues2 ?? collect(),
                    $children,
                ),
            ];

            if ($variation === $lastVariation) {
                $first = $optValue->productOptionValues2->first();
                $price = (float) ($first->price ?? 0);
                $pricePrefix = $first->price_prefix ?? '';
                $row += [
                    'price' => $pricePrefix === '+' ? $price : -$price,
                    'price_prefix' => $pricePrefix,
                    'quantity' => (int) ($first->quantity ?? 0),
                ];
            }

            $data[$index] = $row;
        }

        return [$data, $images];
    }

    protected function buildOptionValues2(Collection $values2, ?array $children): array
    {
        $out = [];
        $childType = $children['type'] ?? null;

        foreach ($values2 as $i => $v2) {
            $price = (float) $v2->price;
            $signed = match ($v2->price_prefix) {
                '+' => $price,
                '-' => -$price,
                default => 0.0,
            };

            $row = [
                'id' => $v2->id,
                'option_value_2_id' => $v2->option_value_2_id,
                'name' => $children['name'] ?? null,
                'value' => $v2->optionValue?->description?->name ?? '',
                'price' => $signed,
                'price_prefix' => $v2->price_prefix ?? '',
                'quantity' => (int) ($v2->quantity ?? 0),
                'type' => $children['type'] ?? '',
                'variation' => $children['variation'] ?? null,
            ];

            if ($childType === 'image') {
                $row['image'] = resizeImage($v2->optionValue?->image ?? '', 50, 50, 'client');
            }

            $out[$i] = $row;
        }

        return $out;
    }
}
