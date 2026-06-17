<?php

namespace App\Services;

use App\Models\Entities\Product;
use App\Models\Entities\ProductVariant;
use Illuminate\Support\Collection;

class ProductOptionService
{
    public function buildOptions(Product $product): array
    {
        $hasVariants = (bool) ($product->has_variants ?? false);

        $variantSection = $hasVariants
            ? $this->buildVariantOptions($product)
            : ['optionGroups' => [], 'variantSwatchImages' => []];

        $customFieldOptions = $this->buildCustomFieldOptions($product);

        $optionGroups = [...$variantSection['optionGroups'], ...$customFieldOptions];

        return [
            'optionGroups'        => $optionGroups,
            'variantSwatchImages' => $variantSection['variantSwatchImages'],
            'variantMatrix'  => $hasVariants ? $this->buildVariantMatrix($product) : [],
            'defaultVariant' => $hasVariants ? $this->resolveDefaultVariant($product) : null,
            'variantGallery' => $hasVariants ? $this->buildVariantGallery($product) : [],
        ];
    }

    protected function buildVariantGallery(Product $product): array
    {
        if (! $product->relationLoaded('productImages')) {
            return [];
        }

        $gallery = [];
        foreach ($product->productImages as $img) {
            $variantId = $img->product_variant_id;
            if ($variantId === null) {
                continue;
            }
            $gallery[(int) $variantId][] = [
                'image'      => (string) $img->image,
                'alt'        => (string) ($img->alt ?? ''),
                'sort_order' => (int) ($img->sort_order ?? 0),
            ];
        }

        return $gallery;
    }

    protected function buildVariantOptions(Product $product): array
    {
        $productVariants = $product->productVariants ?? collect();
        if ($productVariants->isEmpty()) {
            return ['optionGroups' => [], 'variantSwatchImages' => []];
        }

        $optionMeta = [];
        $optionValuesByOption = [];
        $variantImages = [];

        foreach ($productVariants as $variant) {
            $image = (string) ($variant->image ?? '');
            foreach ($variant->productVariantAttributes ?? [] as $attr) {
                $optionId      = (int) $attr->option_id;
                $optionValueId = (int) $attr->option_value_id;

                if ($attr->option) {
                    $optionMeta[$optionId] ??= $attr->option;
                }
                if ($attr->optionValue) {
                    $optionValuesByOption[$optionId][$optionValueId] ??= $attr->optionValue;
                }
                if ($image !== '') {
                    $variantImages[$optionValueId][$image] = true;
                }
            }
        }
        $variantImages = array_map(
            fn (array $set) => array_key_first($set),
            array_filter($variantImages, fn (array $set) => count($set) === 1),
        );

        uasort(
            $optionMeta,
            fn ($a, $b) => [(int) ($a->sort_order ?? 0), (int) $a->id]
                <=> [(int) ($b->sort_order ?? 0), (int) $b->id],
        );

        $optionGroups = [];
        $swatchImages = [];
        $index        = 0;

        foreach ($optionMeta as $optionId => $option) {
            $optionValues = $optionValuesByOption[$optionId] ?? [];
            if (empty($optionValues)) {
                continue;
            }

            $optionType = (string) $option->type;
            [$selectableValues, $rowImages] = $this->buildImageAndOptionValues(
                collect($optionValues),
                $optionType,
                $variantImages,
            );

            $swatchImages = array_merge($swatchImages, $rowImages);

            $optionGroups[$index++] = [
                'option_id'        => $optionId,
                'value'            => null,
                'required'         => true,
                'type'             => $optionType,
                'name_display'     => $option->description?->name_display ?? $option->description?->name,
                'role'             => getCoreConfig('option.role_variant'),
                'selectableValues' => $selectableValues,
            ];
        }

        return ['optionGroups' => $optionGroups, 'variantSwatchImages' => $swatchImages];
    }

    protected function buildImageAndOptionValues(Collection $optionValues, string $optionType, array $variantImages = []): array
    {
        $rows = [];
        $images = [];

        foreach ($optionValues as $ov) {
            $name         = (string) ($ov->description?->name ?? '');
            $optionValueImage = (string) ($ov->image ?? '');
            $variantImage = $variantImages[(int) $ov->id] ?? '';

            if ($optionType === 'image') {
                $thumbnail = $variantImage !== '' ? $variantImage : $optionValueImage;
                if ($thumbnail !== '') {
                    $images[] = [
                        'key'   => 'opt'.$ov->id,
                        'image' => $thumbnail,
                        'alt'   => $name,
                    ];
                }
            }

            $rows[] = [
                'id'                => (int) $ov->id,
                'option_value_id'   => (int) $ov->id,
                'name'              => $name,
                'image'             => $optionValueImage,
                'variant_image'     => $variantImage
            ];
        }

        return [$rows, $images];
    }

    protected function buildCustomFieldOptions(Product $product): array
    {
        $productOptions = ($product->productOptions ?? collect())
            ->filter(fn ($productOption) => $productOption->option?->role === getCoreConfig('option.role_custom_field'));

        if ($productOptions->isEmpty()) {
            return [];
        }

        $optionGroups = [];
        $index = 0;

        foreach ($productOptions as $productOption) {
            $option = $productOption->option;
            $selectableValues = ($productOption->productOptionValues ?? collect())
                ->map(fn ($productOptionValue) => [
                    'id'              => (int) $productOptionValue->id,
                    'option_value_id' => (int) $productOptionValue->option_value_id,
                    'name'            => (string) ($productOptionValue->optionValue?->description?->name ?? ''),
                    'image'           => (string) ($productOptionValue->image ?? ''),
                    'product_id'      => (int) $productOptionValue->product_id,
                    'option_id'       => (int) $productOptionValue->option_id,
                ])
                ->values()
                ->all();

            $optionId = (int) ($option->id ?? $productOption->option_id ?? 0);

            $optionGroups[$index++] = [
                'option_id'        => $optionId,
                'value'            => $productOption->value,
                'required'         => (bool) ($productOption->required ?? false),
                'type'             => (string) $option->type,
                'name_display'     => $option->description?->name_display ?? $option->description?->name,
                'role'             => getCoreConfig('option.role_custom_field'),
                'selectableValues' => $selectableValues,
            ];
        }

        return $optionGroups;
    }

    protected function buildVariantMatrix(Product $product): array
    {
        $variants = $product->productVariants ?? collect();
        $matrix = [];

        foreach ($variants as $variant) {
            $attributes = [];
            foreach ($variant->productVariantAttributes ?? [] as $attr) {
                $attributes[(int) $attr->option_id] = (int) $attr->option_value_id;
            }
            ksort($attributes);

            $stock = $variant->productStock;
            $available = $stock ? $stock->sellableQuantity() : 999;
            $subtract = $stock ? (bool) $stock->subtract : false;

            [$effectivePrice, $strikePrice, $special] = $this->resolveVariantPricing($variant);

            $matrix[] = [
                'id'              => (int) $variant->id,
                'sku'             => $variant->sku,
                'signature'       => $variant->attribute_signature,
                'attributes'      => $attributes,
                'price'           => (float) $variant->price,
                'regular_price'   => $variant->regular_price !== null ? (float) $variant->regular_price : null,
                // Giá user thực sự trả khi mua variant này tại thời điểm hiện
                // tại — special > variant.price. Frontend chỉ đọc field này
                // để show #price-product, không cần biết special tồn tại.
                'effective_price' => $effectivePrice,
                // Giá tham chiếu strike-through (= MSRP nếu set, fallback
                // variant.price khi đang trong campaign). Frontend show khi
                // strike > effective. Null = không có gì để strike.
                'strike_price'    => $strikePrice,
                // Campaign info (id + dates) cho countdown / badge "Đang sale
                // tới HH:MM". null = không có campaign active.
                'special'         => $special,
                'image'           => $variant->image,
                'is_default'      => (bool) $variant->is_default,
                'available'       => $available,
                'subtract'        => $subtract,
                'has_stock'       => $stock !== null,
                'label'           => $variant->description?->label,
                'note'            => $variant->description?->note,
            ];
        }

        return $matrix;
    }

    protected function resolveDefaultVariant(Product $product): ?array
    {
        $variants = $product->productVariants ?? collect();
        $default = $variants->sortBy([
            ['is_default', 'desc'],
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])->first();

        if (! $default) {
            return null;
        }

        $attributes = [];
        foreach ($default->productVariantAttributes ?? [] as $attr) {
            $attributes[(int) $attr->option_id] = (int) $attr->option_value_id;
        }
        ksort($attributes);

        $stock = $default->productStock;

        [$effectivePrice, $strikePrice, $special] = $this->resolveVariantPricing($default);

        return [
            'id'              => (int) $default->id,
            'price'           => (float) $default->price,
            'regular_price'   => $default->regular_price !== null ? (float) $default->regular_price : null,
            'effective_price' => $effectivePrice,
            'strike_price'    => $strikePrice,
            'special'         => $special,
            'image'           => $default->image,
            'attributes'      => $attributes,
            'available'       => $stock ? $stock->sellableQuantity() : 0,
            'sku'             => $default->sku,
            'label'           => $default->description?->label,
            'note'            => $default->description?->note,
        ];
    }

    /**
     * Tính ba mảnh hiển thị giá variant — gộp 1 chỗ để buildVariantMatrix +
     * resolveDefaultVariant không drift logic:
     *
     *   effective_price = COALESCE(variantSpecial.price, variant.price)
     *   strike_price    = giá để gạch ngang khi đang có giảm (variant.price
     *                     khi special active; regular_price khi không có
     *                     special; null khi không gạch ngang)
     *   special         = ['id' => int, 'price' => float, 'date_end' => str|null]
     *                     hoặc null
     *
     * Logic strike: chỉ trả giá tham chiếu nếu strike > effective. Tránh
     * UI show "1.000.000đ" gạch ngang lên đè "1.000.000đ" giá hiện tại.
     */
    protected function resolveVariantPricing(ProductVariant $productVariant): array
    {
        $basePrice = (float) $productVariant->price;
        $regular = $productVariant->regular_price !== null ? (float) $productVariant->regular_price : null;

        $special = null;
        $effective = $basePrice;
        if ($productVariant->relationLoaded('productVariantSpecial') && $productVariant->productVariantSpecial) {
            $vs = $productVariant->productVariantSpecial;
            $effective = (float) $vs->price;
            $special = [
                'id'         => (int) $vs->id,
                'price'      => $effective,
                'date_end'   => $vs->date_end?->format('Y-m-d H:i:s'),
                'date_start' => $vs->date_start?->format('Y-m-d H:i:s'),
            ];
        }

        $strike = null;
        if ($regular !== null && $regular > $effective) {
            $strike = $regular;
        } elseif ($special !== null && $basePrice > $effective) {
            $strike = $basePrice;
        }

        return [$effective, $strike, $special];
    }
}
