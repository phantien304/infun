<?php

namespace App\Services;

use App\Models\Entities\Product;
use Illuminate\Support\Collection;

class ProductOptionService
{
    public function buildOptions(Product $product): array
    {
        $hasVariants = (bool) ($product->has_variants ?? false);

        $variantSection = $hasVariants
            ? $this->buildVariantOptions($product)
            : ['options' => [], 'imageOptions' => []];

        $customFieldOptions = $this->buildCustomFieldOptions($product);

        $options = [...$variantSection['options'], ...$customFieldOptions];

        return [
            'options'        => $options,
            'imageOptions'   => $variantSection['imageOptions'],
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
            return ['options' => [], 'imageOptions' => []];
        }

        $variantDeclarations = ($product->productOptions ?? collect())
            ->filter(fn ($po) => $po->option?->role === getCoreConfig('option.role_variant'));

        if ($variantDeclarations->isEmpty()) {
            return ['options' => [], 'imageOptions' => []];
        }

        $optionValuesByVariant = [];
        $variantImages = [];
        foreach ($productVariants as $variant) {
            $image = (string) ($variant->image ?? '');
            foreach ($variant->productVariantAttributes ?? [] as $attr) {
                $optionId      = (int) $attr->option_id;
                $optionValueId = (int) $attr->option_value_id;

                if ($attr->optionValue) {
                    $optionValuesByVariant[$optionId][$optionValueId] ??= $attr->optionValue;
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

        $options = [];
        $images  = [];
        $index   = 0;

        foreach ($variantDeclarations as $value) {
            $option   = $value->option;
            $optionId = (int) $option->id;

            $optionValues = $optionValuesByVariant[$optionId] ?? [];
            if (empty($optionValues)) {
                continue;
            }

            $optionType = (string) $option->type;
            [$rows, $rowImages] = $this->buildImageAndOptionValues(
                collect($optionValues),
                $optionType,
                $variantImages,
            );

            $images = array_merge($images, $rowImages);

            $options[$index++] = [
                'id'                    => $optionId,
                'value'                 => null,
                'required'              => (bool) ($value->required ?? true),
                'option_id'             => $optionId,
                'option_name'           => $option->description?->name,
                'option_type'           => $optionType,
                'name_display'          => $option->description?->name_display ?? $option->description?->name,
                'role'                  => getCoreConfig('option.role_variant'),
                'product_option_values' => $rows,
            ];
        }

        return ['options' => $options, 'imageOptions' => $images];
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
            ->filter(fn ($value) => $value->option?->role === getCoreConfig('option.role_custom_field'));

        if ($productOptions->isEmpty()) {
            return [];
        }

        $data = [];
        $index = 0;

        foreach ($productOptions as $po) {
            $option = $po->option;
            $values = ($po->productOptionValues ?? collect())
                ->map(fn ($pov) => [
                    'id'              => (int) $pov->id,
                    'option_value_id' => (int) $pov->option_value_id,
                    'name'            => (string) ($pov->optionValue?->description?->name ?? ''),
                    'image'           => (string) ($pov->image ?? ''),
                    'product_id'      => (int) $pov->product_id,
                    'option_id'       => (int) $pov->option_id,
                ])
                ->values()
                ->all();

            $optionId = (int) ($option->id ?? $po->option_id ?? 0);

            $data[$index++] = [
                'id'                    => $optionId,
                'value'                 => $po->value,
                'required'              => (bool) ($po->required ?? false),
                'option_id'             => $optionId,
                'option_name'           => $option->description?->name,
                'option_type'           => (string) $option->type,
                'name_display'          => $option->description?->name_display ?? $option->description?->name,
                'role'                  => getCoreConfig('option.role_custom_field'),
                'product_option_values' => $values,
            ];
        }

        return $data;
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
            // Stock NULL = chưa có row product_stock (data drift / seed thiếu /
            // eager-load relation fail). KHÔNG fallback về (available=0,
            // subtract=true) — rule đó sẽ làm UI grey toàn bộ swatch ngay từ
            // init khi data có vấn đề. Thay bằng (available=999, subtract=false)
            // = "không track tồn" → variant pickable, user vẫn add-to-cart
            // được. Stock thực sẽ ép tại OrderService khi tạo order.
            $available = $stock ? (int) $stock->available : 999;
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
            'available'       => $stock ? (int) $stock->available : 0,
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
    protected function resolveVariantPricing(\App\Models\Entities\ProductVariant $variant): array
    {
        $basePrice = (float) $variant->price;
        $regular = $variant->regular_price !== null ? (float) $variant->regular_price : null;

        $special = null;
        $effective = $basePrice;
        if ($variant->relationLoaded('productVariantSpecial') && $variant->productVariantSpecial) {
            $vs = $variant->productVariantSpecial;
            $effective = (float) $vs->price;
            $special = [
                'id'         => (int) $vs->id,
                'price'      => $effective,
                'date_end'   => $vs->date_end?->format('Y-m-d H:i:s'),
                'date_start' => $vs->date_start?->format('Y-m-d H:i:s'),
            ];
        }

        // Ưu tiên reference theo thứ tự: regular_price (MSRP) > variant.price
        // (khi đang trong campaign). Chỉ trả nếu lớn hơn effective.
        $strike = null;
        if ($regular !== null && $regular > $effective) {
            $strike = $regular;
        } elseif ($special !== null && $basePrice > $effective) {
            $strike = $basePrice;
        }

        return [$effective, $strike, $special];
    }
}
