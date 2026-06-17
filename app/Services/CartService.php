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

    public function tryAdd(array $payload): array
    {
        $productId = (int) ($payload['id'] ?? 0);
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $optionPayload = (array) ($payload['option'] ?? []);

        if ($productId <= 0) {
            return ['ok' => false, 'reason' => 'invalid_product'];
        }

        [$productVariantAttributes, $customOptions] = $this->splitOptionPayload($optionPayload);
        $variantId = $this->resolveVariantId($productId, $productVariantAttributes);

        $key = $this->makeKey($productId, $variantId, $customOptions);
        $alreadyInCart = (int) (session()->get('cart.'.$key.'.quantity', 0));
        $totalAfter = $alreadyInCart + $quantity;

        if (! getConfigDb('config_stock_checkout')) {
            $this->persistLine($productId, $variantId, $quantity, $productVariantAttributes, $customOptions);
            return ['ok' => true, 'variant_id' => $variantId, 'quantity' => $quantity];
        }

        $product = Product::with(['defaultVariant.productStock'])->find($productId);
        if (! $product) {
            return ['ok' => false, 'reason' => 'invalid_product'];
        }

        $variant = $variantId
            ? ProductVariant::with(['productStock'])->find($variantId)
            : null;

        if (! $this->checkStock($product, $variant, $totalAfter)) {
            return [
                'ok'              => false,
                'reason'          => 'out_of_stock',
                'available'       => $this->resolveAvailable($product, $variant),
                'requested'       => $quantity,
                'already_in_cart' => $alreadyInCart,
            ];
        }

        $this->persistLine($productId, $variantId, $quantity, $productVariantAttributes, $customOptions);

        return ['ok' => true, 'variant_id' => $variantId, 'quantity' => $quantity];
    }

    protected function resolveAvailable(Product $product, ?ProductVariant $variant): int
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

    /**
     * Persist (or merge) one cart line. Shared by add() and tryAdd() so the
     * two entry points stay in lock-step on session shape.
     */
    protected function persistLine(
        int $productId,
        ?int $variantId,
        int $quantity,
        array $variantAttributes,
        array $customOptions,
    ): void {
        $key = $this->makeKey($productId, $variantId, $customOptions);

        $existing = session()->get('cart.'.$key);
        if ($existing) {
            session()->put('cart.'.$key.'.quantity', (int) $existing['quantity'] + $quantity);
        } else {
            session()->put('cart.'.$key, [
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'quantity'           => $quantity,
                'variant_attributes' => $variantAttributes,
                'custom_options'     => $customOptions,
            ]);
        }

        $this->resolvedItems = null;
    }

    public function update(string $key, int $qty): void
    {
        if ($qty > 0) {
            session()->put('cart.'.$key.'.quantity', $qty);
        } else {
            session()->forget('cart.'.$key);
        }
        $this->resolvedItems = null;
    }

    public function remove(string $key): void
    {
        session()->forget('cart.'.$key);
        $this->resolvedItems = null;
    }

    public function clear(): void
    {
        session()->forget('cart');
        session()->forget('total_cart_header');
        // reward: điểm thưởng user chọn áp vào đơn (CheckoutTotalService đọc
        // session('reward')). Phải reset khi order xong để không rò sang đơn sau.
        session()->forget('reward');
        session()->forget('checkout.applied_coupons');
        session()->forget('checkout.applied_gifts');
        session()->forget('checkout.applied_vouchers');
        $this->resolvedItems = null;
    }

    public function hasItems(): bool
    {
        return count(session()->get('cart', [])) > 0;
    }

    public function getItems(): array
    {
        if ($this->resolvedItems !== null) {
            return $this->resolvedItems;
        }

        $raw = session()->get('cart', []);
        if (empty($raw)) {
            session()->put('cart_shipping', $this->shipping);

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
                // Metadata option (name/type) cho buildVariantDisplay — không
                // eager-load thì $attr->option lazy-load mỗi attribute mỗi dòng
                // giỏ (N+1) khi render cart/checkout.
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

        session()->put('cart_shipping', $this->shipping);

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

        foreach ($payload as $outerId => $entry) {
            $optionId = (int) ($entry['option_id'] ?? 0);
            $role = (int) ($roles[$optionId] ?? getCoreConfig('option.role_custom_field'));

            if ($role === getCoreConfig('option.role_variant')) {
                // Blade `_option.blade.php` emit key `option_value_id` cho
                // variant role (xem $valueParam = $isVariant ? 'option_value_id'
                // : 'product_option_value_id'). Đọc nhầm key sẽ ra mảng rỗng
                // → resolveVariantId trả null → cart add ở product level
                // không biết variant nào → giá sai + SKU sai khi tạo order.
                $values = $entry['option_value_id'] ?? ($entry['product_option_value_id'] ?? null);
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
                'product_option_id' => (int) $outerId,
                'option_id'         => $optionId,
                'name'              => (string) ($entry['name'] ?? ''),
                'type'              => (string) ($entry['type'] ?? ''),
                'variation'         => (int) ($entry['variation'] ?? 2),
                'required'          => (int) ($entry['required'] ?? 0),
                'value'             => (string) ($entry['value'] ?? ''),
            ];
        }

        // Sort variant attributes deterministic (option_id ASC) cho signature
        // ổn định — match với cách lookup ở resolveVariantId.
        usort($productVariantAttributes, fn ($a, $b) => $a['option_id'] <=> $b['option_id']);

        return [$productVariantAttributes, $customOptions];
    }

    /**
     * Resolve product_variant_id from (product_id, [(option_id, option_value_id)]).
     *
     * Strategy: walk the product's variants, compare attribute sets. A
     * variant with the same attribute count and every (option_id =>
     * option_value_id) pair matching is a hit.
     *
     * Simple products (no payload attributes) used to return NULL and have
     * every downstream caller branch on it. Post-unify migration, every
     * product owns a default variant, so we look that one up and link the
     * cart line to it — stock + audit then go through product_stock for
     * every line uniformly. Falls back to NULL only if the default variant
     * is genuinely missing (data drift; downstream code keeps the legacy
     * product.quantity path as a safety net).
     */
    protected function resolveVariantId(int $productId, array $productVariantAttributes): ?int
    {
        if (empty($productVariantAttributes)) {
            return $this->resolveDefaultVariantId($productId);
        }

        $needed = collect($productVariantAttributes)
            ->mapWithKeys(fn ($a) => [(int) $a['option_id'] => (int) $a['option_value_id']]);

        $rows = ProductVariantAttribute::query()
            ->join('product_variant', 'product_variant.id', '=', 'product_variant_attribute.product_variant_id')
            ->where('product_variant.product_id', $productId)
            ->whereNull('product_variant.deleted_at')
            ->select('product_variant_attribute.product_variant_id', 'product_variant_attribute.option_id', 'product_variant_attribute.option_value_id')
            ->get()
            ->groupBy('product_variant_id');

        foreach ($rows as $variantId => $attrs) {
            if ($attrs->count() !== $needed->count()) {
                continue;
            }
            $match = true;
            foreach ($attrs as $a) {
                if (($needed[(int) $a->option_id] ?? null) !== (int) $a->option_value_id) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return (int) $variantId;
            }
        }

        return null;
    }

    /**
     * Lookup the default variant id for a product. Simple products own
     * exactly one (is_default = 1) thanks to the unify migration; variant
     * products usually have one too (the primary tuple admins flag is_default).
     * Returns null only on data drift — caller falls back to legacy path.
     */
    protected function resolveDefaultVariantId(int $productId): ?int
    {
        $id = ProductVariant::query()
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Giá tính tiền cho 1 line cart:
     *  - Có variant: COALESCE(variantSpecial.price, variant.price). Variant
     *    KHÔNG dùng product_special (Hướng B, xem CLAUDE.md). Logic mirror
     *    ProductOptionService::resolveVariantPricing để cart total + UI detail
     *    page nhất quán — user click variant thấy 800k, vào cart cũng 800k.
     *  - Không variant: COALESCE(productSpecial.price, product.price). Đường
     *    đi cũ giữ nguyên cho simple product.
     */
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

    /**
     * Decide whether `$quantity` more units of this product can enter the
     * cart. Single source of truth: product_stock + inventory_policy.
     *
     * The check honours three policies (see core.stock.policy):
     *  - DENY      → block once available drops below the requested qty
     *  - BACKORDER → always green-light; admin sees the backlog via
     *                stock_movement (`bán khống` semantics)
     *  - UNTRACKED → always green-light; the row exists only to keep the
     *                cart pipeline uniform
     *
     * Resolution order:
     *  1. Variant carried on the cart line → its productStock.
     *  2. Product's default variant → its productStock. Covers cart lines
     *     for simple products that haven't been linked yet.
     *
     * No legacy product.quantity / product.subtract fallback. Per the
     * pseudo-variant decision, every product MUST own a default variant
     * + product_stock row (created by the unify migration). Anything
     * missing that pair is a data error and the gate stays strict —
     * silently falling back to legacy columns was the source of the
     * earlier "available 30 but rejected" drift on product 46819.
     */
    protected function checkStock(Product $product, ?ProductVariant $variant, int $quantity): bool
    {
        if (! getConfigDb('config_stock_checkout')) {
            return true;
        }

        $stock = $variant?->productStock
            ?? $product->defaultVariant?->productStock;

        if (! ($stock instanceof ProductStock)) {
            // Missing stock row = unmigrated product. Block until admin
            // backfills; refusing here surfaces the data issue instead of
            // hiding it behind product.quantity.
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

    /**
     * Build cấu trúc `option` cho blade hiển thị (cart/checkout). Mỗi entry =
     * 1 option group user đã chọn, kèm value đã resolve. Format giữ tương
     * thích với template cũ (`$opt['name']`, `$opt['value']`, `$opt['variation']`,
     * `$opt['child']` rỗng) để không phá blade.
     */
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
                    'product_option_id'       => 0,
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
                'product_option_id'       => $custom['product_option_id'] ?? 0,
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

    protected function makeKey(int $productId, ?int $variantId, array $customOptions): string
    {
        $payload = json_encode([
            'v' => $variantId,
            'c' => array_map(fn ($c) => $c['product_option_id'].':'.$c['value'], $customOptions),
        ]);

        return $productId.':'.md5($payload).$productId;
    }
}
