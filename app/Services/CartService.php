<?php

namespace App\Services;

use App\Models\Entities\Option;
use App\Models\Entities\Product;
use App\Models\Entities\ProductOption;
use App\Models\Entities\ProductStock;
use App\Models\Entities\ProductVariant;
use App\Models\Entities\ProductVariantAttribute;
use Illuminate\Support\Facades\DB;

class CartService
{
    protected ?array $resolvedItems = null;

    protected array $shipping = ['width' => 0, 'height' => 0, 'length' => 0, 'weight' => 0];

    public function tryAdd(array $payload, Product $product): array
    {
        $productId = (int) $product->id;
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $optionPayload = (array) ($payload['option'] ?? []);

        [$productVariantAttributes, $customOptions] = $this->splitOptionPayload($optionPayload);
        $variantId = $this->resolveVariantId($productId, $productVariantAttributes);

        $keySession = $this->makeKeySession($productId, $variantId, $customOptions);
        $quantityInCart = (int) (session()->get(getCoreConfig('session.cart').'.'.$keySession.'.quantity', 0));
        $totalQuantity = $quantityInCart + $quantity;

        if (! getConfigDb('config_stock_checkout')) {
            $this->persistLine($productId, $variantId, $quantity, $productVariantAttributes, $customOptions);
            return ['ok' => true, 'variant_id' => $variantId, 'quantity' => $quantity];
        }

        $product->load('defaultVariant.productStock');
        $variant = $variantId
            ? ProductVariant::with(['productStock'])->find($variantId)
            : null;

        if (! $this->checkStock($product, $variant, $totalQuantity)) {
            return [
                'ok'              => false,
                'available'       => $this->resolveQuantityAvailable($product, $variant),
                'quantity'       => $quantity,
                'total_in_cart' => $quantityInCart,
            ];
        }

        $this->persistLine($productId, $variantId, $quantity, $productVariantAttributes, $customOptions);

        return ['ok' => true, 'variant_id' => $variantId, 'quantity' => $quantity];
    }

    protected function resolveQuantityAvailable(Product $product, ?ProductVariant $variant): int
    {
        $stock = $variant?->productStock ?? $product->defaultVariant?->productStock;

        if (! ($stock instanceof ProductStock)) {
            return 0;
        }

        $policy = (int) ($stock->inventory_policy ?? getCoreConfig('stock.policy.deny'));
        if (
            $policy === (int) getCoreConfig('stock.policy.untracked')
            || $policy === (int) getCoreConfig('stock.policy.backorder')
        ) {
            return PHP_INT_MAX;
        }

        $onHand   = (int) ($stock->on_hand ?? 0);
        $reserved = (int) ($stock->reserved ?? 0);

        return max(0, $onHand - $reserved);
    }

    protected function persistLine(
        int $productId,
        ?int $variantId,
        int $quantity,
        array $variantAttributes,
        array $customOptions,
    ): void {
        $keySession = $this->makeKeySession($productId, $variantId, $customOptions);

        $existing = session()->get(getCoreConfig('session.cart').'.'.$keySession);
        if ($existing) {
            session()->put(getCoreConfig('session.cart').'.'.$keySession.'.quantity', (int) $existing['quantity'] + $quantity);
        } else {
            session()->put(getCoreConfig('session.cart').'.'.$keySession, [
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'quantity'           => $quantity,
                'variant_attributes' => $variantAttributes,
                'custom_options'     => $customOptions,
            ]);
        }

        $this->resolvedItems = null;
    }

    public function update(string $keySession, int $qty): void
    {
        if ($qty > 0) {
            session()->put(getCoreConfig('session.cart').'.'.$keySession.'.quantity', $qty);
        } else {
            session()->forget(getCoreConfig('session.cart').'.'.$keySession);
        }
        $this->resolvedItems = null;
    }

    public function remove(string $keySession): void
    {
        session()->forget(getCoreConfig('session.cart').'.'.$keySession);
        $this->resolvedItems = null;
    }

    public function clear(): void
    {
        session()->forget(getCoreConfig('session.cart'));
        session()->forget(getCoreConfig('session.cart_header'));
        session()->forget(getCoreConfig('session.reward'));
        session()->forget(getCoreConfig('session.applied_coupons'));
        session()->forget(getCoreConfig('session.applied_gifts'));
        session()->forget(getCoreConfig('session.applied_vouchers'));
        $this->resolvedItems = null;
    }

    public function hasItems(): bool
    {
        return count(session()->get(getCoreConfig('session.cart'), [])) > 0;
    }

    public function getItems(): array
    {
        if ($this->resolvedItems !== null) {
            return $this->resolvedItems;
        }

        $raw = session()->get(getCoreConfig('session.cart'), []);
        if (empty($raw)) {
            session()->put(getCoreConfig('session.cart_shipping'), $this->shipping);

            return $this->resolvedItems = [];
        }

        $productIds = collect($raw)->pluck('product_id')->unique()->all();
        $variantIds = collect($raw)->pluck('product_variant_id')->filter()->unique()->all();

        $products = Product::with([
            'description',
            'productSpecial',
            'weightClass',
            'defaultVariant.productStock',
        ])->whereIn('id', $productIds)->dateAvailable()->get()->keyBy('id');

        $variants = $variantIds
            ? ProductVariant::with([
                'productStock',
                'description',
                'productVariantAttributes.optionValue.description',
                'productVariantAttributes.option.description',
                'productVariantSpecial',
            ])->whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        $items = [];
        $this->shipping = ['width' => 0, 'height' => 0, 'length' => 0, 'weight' => 0];

        foreach ($raw as $key => $row) {
            $product = $products->get($row['product_id'] ?? 0);
            if (! $product) {
                $this->remove($key);
                continue;
            }

            $variant = isset($row['product_variant_id']) ? $variants->get($row['product_variant_id']) : null;

            $price = $this->resolvePrice($product, $variant);
            $stockOk = $this->checkStock($product, $variant, (int) $row['quantity']);

            $desc = $product->description;
            $name = $desc->name ?? '';
            $slug = resolveSlug($desc->slug ?? null, $name);
            $image = $product->image;

            $variantLabel = $this->buildVariantLabel($variant);
            $variantDisplay = $this->buildVariantDisplay($row, $variant);

            $quantity = (int) $row['quantity'];
            $items[$key] = [
                'key'                => $key,
                'id'                 => $product->id,
                'product_variant_id' => $variant?->id,
                'name'               => $name,
                'model'              => $product->model,
                'image'              => $image,
                'shipping'           => $product->shipping,
                'quantity'           => $quantity,
                'minimum'            => $product->minimum,
                'subtract'           => $product->subtract,
                'in_stock'           => $stockOk,
                'price'              => $price,
                'total'              => $price * $quantity,
                'reward'             => 0,
                'points'             => 0,
                'weight'             => ($product->weight ?? 0) * $quantity,
                'weight_class_id'    => $product->weight_class_id,
                'length'             => $product->length,
                'width'              => $product->width,
                'height'             => $product->height,
                'length_class_id'    => $product->length_class_id,
                'url'                => buildUrl($slug, getModuleConfig('url.product'), $product->id),
                'variant_label'      => $variantLabel,
                'option'             => $variantDisplay,
                'custom_options'     => $row['custom_options'] ?? [],
            ];

            if ($product->shipping) {
                $this->shipping['width']  = max($this->shipping['width'], (int) $product->width);
                $this->shipping['height'] = max($this->shipping['height'], (int) $product->height);
                $this->shipping['length'] = max($this->shipping['length'], (int) $product->length);
                $this->shipping['weight'] += (int) (($product->weight ?? 0) * $quantity);
            }
        }

        session()->put(getCoreConfig('session.cart_shipping'), $this->shipping);

        return $this->resolvedItems = $items;
    }

    public function getSubtotal(): int
    {
        return array_sum(array_column($this->getItems(), 'total'));
    }

    public function countItems(): int
    {
        return array_sum(array_column($this->getItems(), 'quantity'));
    }

    public function hasStock(): bool
    {
        foreach ($this->getItems() as $item) {
            if (! $item['in_stock']) {
                return false;
            }
        }

        return true;
    }

    public function validateMinimum(): ?array
    {
        $items = $this->getItems();
        $countByProduct = [];
        foreach ($items as $item) {
            $countByProduct[$item['id']] = ($countByProduct[$item['id']] ?? 0) + (int) $item['quantity'];
        }
        foreach ($items as $item) {
            $minimum = (int) ($item['minimum'] ?? 0);
            if ($minimum > 0 && ($countByProduct[$item['id']] ?? 0) < $minimum) {
                return ['name' => $item['name'], 'minimum' => $minimum];
            }
        }

        return null;
    }

    protected function splitOptionPayload(array $payload): array
    {
        if (empty($payload)) {
            return [[], []];
        }

        $optionIds = [];
        foreach ($payload as $entry) {
            $oid = (int) ($entry['option_id'] ?? 0);
            if ($oid > 0) {
                $optionIds[] = $oid;
            }
        }
        $roles = Option::whereIn('id', array_unique($optionIds))->pluck('role', 'id');

        $productVariantAttributes = [];
        $customOptions = [];
        foreach ($payload as $entry) {
            $optionId = (int) ($entry['option_id'] ?? 0);
            $role = (int) ($roles[$optionId] ?? getCoreConfig('option.role_custom_field'));

            if ($role === getCoreConfig('option.role_variant')) {
                $values = $entry['option_value_id'] ?? null;
                if (is_array($values)) {
                    foreach ($values as $v) {
                        if ((int) $v > 0) {
                            $productVariantAttributes[] = ['option_id' => $optionId, 'option_value_id' => (int) $v];
                        }
                    }
                } elseif ((int) $values > 0) {
                    $productVariantAttributes[] = ['option_id' => $optionId, 'option_value_id' => (int) $values];
                }
                continue;
            }

            $customOptions[] = [
                'option_id'         => $optionId,
                'name'              => (string) ($entry['name'] ?? ''),
                'type'              => (string) ($entry['type'] ?? ''),
                'variation'         => (int) ($entry['variation'] ?? 2),
                'required'          => (int) ($entry['required'] ?? 0),
                'value'             => (string) ($entry['value'] ?? ''),
            ];
        }
        usort($productVariantAttributes, fn ($a, $b) => $a['option_id'] <=> $b['option_id']);

        return [$productVariantAttributes, $customOptions];
    }

    protected function resolveVariantId(int $productId, array $productVariantAttributes): ?int
    {
        if (empty($productVariantAttributes)) {
            return $this->resolveDefaultVariantId($productId);
        }

        $needed = collect($productVariantAttributes)
            ->mapWithKeys(fn ($a) => [(int) $a['option_id'] => (int) $a['option_value_id']]);

        $signature = ProductVariant::buildAttributeSignature($needed->all());
        $id = ProductVariant::query()
            ->where('product_id', $productId)
            ->where('attribute_signature', $signature)
            ->value('id');
        if ($id) {
            return (int) $id;
        }

        return null;
    }

    protected function resolveDefaultVariantId(int $productId): ?int
    {
        $id = ProductVariant::query()
            ->where('product_id', $productId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function resolvePrice(Product $product, ?ProductVariant $variant): int
    {
        if ($variant) {
            $vs = $variant->productVariantSpecial;
            if ($vs) {
                return (int) $vs->price;
            }

            return (int) $variant->price;
        }

        $special = $product->productSpecial;
        if ($special) {
            return (int) $special->price;
        }

        return (int) $product->price;
    }

    protected function checkStock(Product $product, ?ProductVariant $variant, int $quantity): bool
    {
        if (! getConfigDb('config_stock_checkout')) {
            return true;
        }

        $stock = $variant?->productStock
            ?? $product->defaultVariant?->productStock;

        if (! ($stock instanceof ProductStock)) {
            return false;
        }

        return $stock->canSell($quantity);
    }

    protected function buildVariantLabel(?ProductVariant $variant): string
    {
        if (! $variant) {
            return '';
        }
        if ($variant->description && filled($variant->description->name)) {
            return $variant->description->name;
        }
        $parts = [];
        foreach ($variant->productVariantAttributes as $attr) {
            $parts[] = $attr->optionValue?->description?->name ?? '';
        }

        return trim(implode(' / ', array_filter($parts)));
    }

    protected function buildVariantDisplay(array $row, ?ProductVariant $variant): array
    {
        $result = [];

        if ($variant) {
            foreach ($variant->productVariantAttributes as $attr) {
                $option = $attr->option;
                $value = $attr->optionValue;
                $result[] = [
                    'product_option_value_id' => $value?->id,
                    'option_id'               => $attr->option_id,
                    'option_value_id'         => $attr->option_value_id,
                    'product_option_id'       => $attr->option_id,
                    'name'                    => $option?->description?->name ?? '',
                    'type'                    => $option?->type ?? '',
                    'variation'               => 2,
                    'required'                => 1,
                    'value'                   => $value?->description?->name ?? '',
                    'image'                   => $value?->image ?? '',
                    'child'                   => [],
                ];
            }
        }

        foreach ($row['custom_options'] ?? [] as $custom) {
            $result[] = [
                'product_option_value_id' => '',
                'option_id'               => $custom['option_id'] ?? 0,
                'product_option_id'       => $custom['option_id'] ?? 0,
                'image'                   => '',
                'name'                    => $custom['name'] ?? '',
                'type'                    => $custom['type'] ?? '',
                'variation'               => 2,
                'required'                => $custom['required'] ?? 0,
                'value'                   => $custom['value'] ?? '',
                'child'                   => [],
            ];
        }

        return $result;
    }

    protected function makeKeySession(int $productId, ?int $variantId, array $customOptions): string
    {
        $payload = json_encode([
            'v' => $variantId,
            'c' => array_map(fn ($c) => $c['option_id'].':'.$c['value'], $customOptions),
        ]);

        return $productId.':'.md5($payload).$productId;
    }
}
