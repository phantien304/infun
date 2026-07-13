<?php

namespace App\Services\Cart;

use App\Enums\OptionRole;
use App\Enums\StockPolicy;
use App\Models\Entities\Option;
use App\Models\Entities\Product;
use App\Models\Entities\ProductOption;
use App\Models\Entities\ProductOptionValue;
use App\Models\Entities\ProductStock;
use App\Models\Entities\ProductVariant;
use App\Services\Measurement\LengthService;
use App\Services\Measurement\WeightService;
use App\Services\Reward\RewardEarnService;
use App\Services\Stock\StockService;
use App\Services\Stock\WarehouseService;
use Illuminate\Support\Collection;

class CartService
{
    protected ?array $resolvedItems = null;

    protected array $shipping = ['width' => 0, 'height' => 0, 'length' => 0, 'weight' => 0];

    protected ?array $holderReservedMap = null;

    public function __construct(
        protected StockService $stockService,
        protected LengthService $lengthService,
        protected WeightService $weightService,
        protected WarehouseService $warehouseService,
        protected RewardEarnService $rewardEarnService,
    ) {
    }

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

        $variant = $variantId
            ? ProductVariant::with(['productStocks'])->find($variantId)
            : null;

        if (! $variant) {
            return ['ok' => false, 'variant_error' => true];
        }

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

    protected function sellableProductStocks(Product $product, ?ProductVariant $variant): Collection
    {
        $rows = $variant?->productStocks ?? $product->defaultVariant?->productStocks;
        if (! ($rows instanceof Collection)) {
            return collect();
        }

        return $rows->whereIn('warehouse_id', $this->warehouseService->sellableWarehouseIds());
    }

    protected function effectivePolicy(Collection $stocks): StockPolicy
    {
        $row = $stocks->firstWhere('warehouse_id', $this->warehouseService->defaultId())
            ?? $stocks->first();

        return $row?->policy() ?? StockPolicy::Deny;
    }

    protected function ownReserved(?int $variantId): int
    {
        if (! $variantId) {
            return 0;
        }
        if ($this->holderReservedMap === null) {
            $this->holderReservedMap = $this->stockService->holderReservedMap((string) session()->getId());
        }

        return (int) ($this->holderReservedMap[$variantId] ?? 0);
    }

    protected function resolveQuantityAvailable(Product $product, ?ProductVariant $productVariant): int
    {
        $productStocks = $this->sellableProductStocks($product, $productVariant);

        if ($productStocks->isEmpty()) {
            return 0;
        }

        if ($this->effectivePolicy($productStocks)->bypassesStockCheck()) {
            return PHP_INT_MAX;
        }

        $variantId = $productVariant?->id ?? $product->defaultVariant?->id;
        $available = (int) $productStocks->sum(fn (ProductStock $s) => $s->sellableQuantity());

        return max(0, $available + $this->ownReserved($variantId ? (int) $variantId : null));
    }

    protected function persistLine(int $productId, ?int $variantId, int $quantity, array $variantAttributes, array $customOptions): void
    {
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

        $cart = session()->get(getCoreConfig('session.cart'), []);
        if (empty($cart)) {
            session()->put(getCoreConfig('session.cart_shipping'), $this->shipping);

            return $this->resolvedItems = [];
        }

        $productIds = collect($cart)->pluck('product_id')->unique()->all();
        $productVariantIds = collect($cart)->pluck('product_variant_id')->filter()->unique()->all();

        $products = Product::with([
            'description',
            'weightClass',
            'defaultVariant.productStocks',
            'defaultVariant.productVariantSpecial',
            'productRewards' => fn ($q) => $q->where('user_group_id', getUserGroupId()),
        ])->whereIn('id', $productIds)->dateAvailable()->get()->keyBy('id');

        $variants = $productVariantIds
            ? ProductVariant::with([
                'productStocks',
                'description',
                'productVariantAttributes.optionValue.description',
                'productVariantAttributes.option.description',
                'productVariantSpecial',
            ])->whereIn('id', $productVariantIds)->get()->keyBy('id')
            : collect();

        $items = [];
        $this->shipping = ['width' => 0, 'height' => 0, 'length' => 0, 'weight' => 0];

        foreach ($cart as $key => $row) {
            $product = $products->get($row['product_id'] ?? 0);
            if (! $product) {
                $this->remove($key);
                continue;
            }

            $variant = isset($row['product_variant_id']) ? $variants->get($row['product_variant_id']) : null;

            $price = $this->resolvePrice($product, $variant)
                + $this->customOptionsSurcharge((int) ($row['product_id'] ?? 0), $row['custom_options'] ?? []);
            $stockOk = $this->checkStock($product, $variant, (int) $row['quantity']);

            $desc = $product->description;
            $name = $desc->name ?? '';
            $slug = resolveSlug($desc->slug ?? null, $name);
            $image = $product->image;

            $variantLabel = $this->buildVariantLabel($variant);
            $variantDisplay = $this->buildVariantDisplay($row, $variant);
            $weightClassId = (int) $product->weight_class_id ?? getConfigDb('config_weight_class_id');
            $lengthClassId = (int) $product->length_class_id ?? getConfigDb('config_length_class_id');

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
                'minimum'            => (int) ($variant?->minimum ?? 1),
                'subtract'           => $product->subtract,
                'in_stock'           => $stockOk,
                'price'              => $price,
                'total'              => $price * $quantity,
                'reward'             => $this->resolveReward($product, $price, $quantity),
                'weight'             => ($product->weight ?? 0) * $quantity,
                'weight_class_id'    => $weightClassId,
                'length'             => $product->length,
                'width'              => $product->width,
                'height'             => $product->height,
                'length_class_id'    => $lengthClassId,
                'url'                => buildUrl($slug, getModuleConfig('url.product'), $product->id),
                'variant_label'      => $variantLabel,
                'option'             => $variantDisplay,
                'custom_options'     => $row['custom_options'] ?? [],
            ];

            if ($product->shipping) {
                $width  = $this->lengthService->convertToSystem((float) $product->width, $lengthClassId);
                $height = $this->lengthService->convertToSystem((float) $product->height, $lengthClassId);
                $length = $this->lengthService->convertToSystem((float) $product->length, $lengthClassId);
                $weight = $this->weightService->convertToSystem((float) ($product->weight ?? 0), $weightClassId);

                $this->shipping['width']  = max($this->shipping['width'], $width);
                $this->shipping['height'] = max($this->shipping['height'], $height);
                $this->shipping['length'] = max($this->shipping['length'], $length);
                $this->shipping['weight'] += $weight * $quantity;
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
        foreach ($this->getItems() as $item) {
            $minimum = (int) ($item['minimum'] ?? 0);
            if ($minimum > 0 && (int) $item['quantity'] < $minimum) {
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
            $role = $roles->get($optionId) ?? OptionRole::CustomField;

            if ($role->isVariant()) {
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

            $rawValueId = $entry['option_value_id'] ?? 0;
            $customOptions[] = [
                'option_id'         => $optionId,
                'option_value_id'   => (int) (is_array($rawValueId) ? ($rawValueId[0] ?? 0) : $rawValueId),
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

    protected function resolveReward(Product $product, int $price, int $quantity): int
    {
        return $this->rewardEarnService->perUnit($product, $price) * $quantity;
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

        $special = $product->defaultVariant?->productVariantSpecial;
        if ($special) {
            return (int) $special->price;
        }

        return (int) $product->price;
    }

    protected function customOptionsSurcharge(int $productId, array $customOptions): int
    {
        if (empty($customOptions) || $productId <= 0) {
            return 0;
        }

        $optionIds = array_values(array_unique(array_filter(
            array_map(fn ($c) => (int) ($c['option_id'] ?? 0), $customOptions)
        )));
        if (empty($optionIds)) {
            return 0;
        }

        $poRows = ProductOption::where('product_id', $productId)
            ->whereIn('option_id', $optionIds)
            ->get(['id', 'option_id', 'price'])
            ->keyBy('option_id');
        if ($poRows->isEmpty()) {
            return 0;
        }

        $valueIds = array_values(array_unique(array_filter(
            array_map(fn ($c) => (int) ($c['option_value_id'] ?? 0), $customOptions)
        )));

        $povPrices = collect();
        if (! empty($valueIds)) {
            $povPrices = ProductOptionValue::whereIn('product_option_id', $poRows->pluck('id')->all())
                ->whereIn('option_value_id', $valueIds)
                ->get(['product_option_id', 'option_value_id', 'price'])
                ->keyBy(fn ($r) => $r->product_option_id.':'.$r->option_value_id);
        }

        $surcharge = 0;
        foreach ($customOptions as $c) {
            $po = $poRows->get((int) ($c['option_id'] ?? 0));
            if (! $po) {
                continue;
            }
            $valueId = (int) ($c['option_value_id'] ?? 0);
            if ($valueId > 0) {
                $surcharge += (int) ($povPrices->get($po->id.':'.$valueId)->price ?? 0);
            } elseif (($c['value'] ?? '') !== '') {
                $surcharge += (int) $po->price;
            }
        }

        return $surcharge;
    }

    protected function checkStock(Product $product, ?ProductVariant $variant, int $quantity): bool
    {
        if (! getConfigDb('config_stock_checkout')) {
            return true;
        }

        $productStocks = $this->sellableProductStocks($product, $variant);

        if ($productStocks->isEmpty()) {
            return false;
        }

        if ($this->effectivePolicy($productStocks)->bypassesStockCheck()) {
            return true;
        }

        $variantId = $variant?->id ?? $product->defaultVariant?->id;
        $available = (int) $productStocks->sum(fn (ProductStock $s) => $s->sellableQuantity())
            + $this->ownReserved($variantId ? (int) $variantId : null);

        return $available >= $quantity;
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
            'c' => array_map(fn ($c) => $c['option_id'].':'.($c['option_value_id'] ?? 0).':'.$c['value'], $customOptions),
        ]);

        return $productId.':'.md5($payload).$productId;
    }
}
