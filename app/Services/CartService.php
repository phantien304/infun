<?php

namespace App\Services;

use App\Models\Entities\Option;
use App\Models\Entities\Product;
use App\Models\Entities\ProductOption;
use App\Models\Entities\ProductVariant;
use App\Models\Entities\ProductVariantAttribute;
use Illuminate\Support\Facades\DB;

/**
 * CartService — quản lý giỏ hàng theo schema cluster variant mới.
 *
 * Khác biệt so với Helpers\Cart legacy:
 *  - Lúc add: resolve combo (option_id => option_value_id) → product_variant_id
 *    qua bảng product_variant_attribute. Variant không tồn tại = invalid, từ chối.
 *  - Giá: đọc product_variant.price (absolute, KHÔNG còn delta '+'/'-').
 *    Fallback product.price khi product simple (has_variants = false).
 *  - Tồn: product_stock.on_hand - product_stock.reserved. Không còn cộng/trừ
 *    quantity của option_value_2.
 *  - Custom field (Option::ROLE_CUSTOM_FIELD): chỉ lưu user input để hiển thị +
 *    ghi xuống orders_product_option khi tạo order; KHÔNG ảnh hưởng giá/tồn/SKU.
 *
 * Session shape:
 *   cart[$key] = [
 *     'product_id'         => int,
 *     'product_variant_id' => ?int,
 *     'quantity'           => int,
 *     'variant_attributes' => [{option_id, option_value_id}],
 *     'custom_options'     => [{product_option_id, option_id, name, type, value, required}],
 *   ]
 *
 * Caller (CheckoutController / blade cart) chỉ dùng `getItems()` để lấy dữ liệu
 * đã enrich. `add/update/remove/clear` mutate session.
 */
class CartService
{
    /** Cache items đã enrich trong 1 request — getItems() có thể bị gọi nhiều
     *  lần (countProducts, hasStock, getSubtotal). */
    protected ?array $cache = null;

    /** Tổng hợp shipping tính lúc enrich items (width/height/length/weight tích
     *  luỹ); ghi xuống session để CheckoutTotalService dùng ở bước tính phí ship. */
    protected array $shipping = ['width' => 0, 'height' => 0, 'length' => 0, 'weight' => 0];

    public function add(array $payload): void
    {
        $productId = (int) ($payload['id'] ?? 0);
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $optionPayload = (array) ($payload['option'] ?? []);

        if ($productId <= 0) {
            return;
        }

        [$productVariantAttributes, $customOptions] = $this->splitOptionPayload($productId, $optionPayload);
        $variantId = $this->resolveVariantId($productId, $productVariantAttributes);

        $key = $this->makeKey($productId, $variantId, $customOptions);

        $existing = session()->get('cart.'.$key);
        if ($existing) {
            session()->put('cart.'.$key.'.quantity', (int) $existing['quantity'] + $quantity);
        } else {
            session()->put('cart.'.$key, [
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'quantity'           => $quantity,
                'variant_attributes' => $productVariantAttributes,
                'custom_options'     => $customOptions,
            ]);
        }

        $this->cache = null;
    }

    public function update(string $key, int $qty): void
    {
        if ($qty > 0) {
            session()->put('cart.'.$key.'.quantity', $qty);
        } else {
            session()->forget('cart.'.$key);
        }
        $this->cache = null;
    }

    public function remove(string $key): void
    {
        session()->forget('cart.'.$key);
        $this->cache = null;
    }

    public function clear(): void
    {
        session()->forget('cart');
        session()->forget('total_cart_header');
        session()->forget('coupon');
        session()->forget('voucher');
        $this->cache = null;
    }

    public function hasItems(): bool
    {
        return count(session()->get('cart', [])) > 0;
    }

    public function getItems(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $raw = session()->get('cart', []);
        if (empty($raw)) {
            session()->put('cart_shipping', $this->shipping);

            return $this->cache = [];
        }

        // Eager-load products + variants 1 lần để tránh N+1 ở foreach
        $productIds = collect($raw)->pluck('product_id')->unique()->all();
        $variantIds = collect($raw)->pluck('product_variant_id')->filter()->unique()->all();

        $products = Product::with([
            'description',
            'productSpecial',
            'weightClass',
        ])->whereIn('id', $productIds)->dateAvailable()->get()->keyBy('id');

        $variants = $variantIds
            ? ProductVariant::with([
                'productStock',
                'description',
                'productVariantAttributes.optionValue.description',
                // Load productVariantSpecial để resolvePrice trả giá campaign
                // khi active, không lấy variant.price tĩnh. Mirror logic detail
                // page (ProductOptionService::resolveVariantPricing).
                'productVariantSpecial',
            ])->whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        $items = [];
        $this->shipping = ['width' => 0, 'height' => 0, 'length' => 0, 'weight' => 0];

        foreach ($raw as $key => $row) {
            $product = $products->get($row['product_id'] ?? 0);
            if (! $product) {
                // Product bị xoá / hết hạn → dọn khỏi cart
                $this->remove($key);
                continue;
            }

            $variant = isset($row['product_variant_id']) ? $variants->get($row['product_variant_id']) : null;

            $price = $this->resolvePrice($product, $variant);
            $stockOk = $this->checkStock($product, $variant, (int) $row['quantity']);

            $name = $product->description->name ?? '';
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
                'stock'              => $stockOk,
                'price'              => $price,
                'total'              => $price * $quantity,
                'reward'             => 0, // ProductReward đọc riêng nếu cần — không gắn vào cart
                'points'             => 0,
                'weight'             => ($product->weight ?? 0) * $quantity,
                'weight_class_id'    => $product->weight_class_id,
                'length'             => $product->length,
                'width'              => $product->width,
                'height'             => $product->height,
                'length_class_id'    => $product->length_class_id,
                'url'                => method_exists($product, 'getUrlClient') ? $product->getUrlClient() : '#',
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

        return $this->cache = $items;
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
            if (! $item['stock']) {
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

    /**
     * Tách form payload thành 2 nhánh: (a) variant attributes — (option_id,
     * option_value_id) để resolve variant; (b) custom_options — input user
     * điền cho custom field.
     *
     * Form contract: `option[$outerId][option_id|product_option_value_id|value|type|...]`
     * `$outerId` = product_option.id (custom field) hoặc option.id (variant). Để
     * phân nhánh chuẩn cần đọc Option.role:
     *  - ROLE_VARIANT  → đưa vào variant_attributes (cần option_id + option_value_id).
     *  - ROLE_CUSTOM_FIELD → đưa vào custom_options.
     */
    protected function splitOptionPayload(int $productId, array $payload): array
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
     * Resolve product_variant_id từ (product_id, [(option_id, option_value_id)]).
     *
     * Strategy: với mỗi variant của product, đối chiếu tập attribute. Variant
     * có cùng số attribute và mọi cặp khớp = match. Trả id; không match = null.
     *
     * Trường hợp product không có variant (simple product) hoặc payload không
     * có variant attribute nào → trả null, caller xử lý fallback giá product.
     */
    protected function resolveVariantId(int $productId, array $productVariantAttributes): ?int
    {
        if (empty($productVariantAttributes)) {
            return null;
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

    protected function checkStock(Product $product, ?ProductVariant $variant, int $quantity): bool
    {
        if (! getConfigDb('config_stock_checkout')) {
            return true;
        }

        if ($variant) {
            $stock = $variant->productStock;
            // Stock NULL = data drift (variant chưa có row product_stock). Cho
            // qua cùng pattern với ProductOptionService::buildVariantMatrix
            // (coi như không track stock) — không chặn add-to-cart vì lý do
            // schema. Stock thực sẽ được verify khi tạo order.
            if (! $stock) {
                return true;
            }
            $available = (int) $stock->on_hand - (int) $stock->reserved;

            return $available >= $quantity;
        }

        if (! $product->subtract) {
            return true;
        }

        return (int) $product->quantity >= $quantity;
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
