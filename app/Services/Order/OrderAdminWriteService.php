<?php

namespace App\Services\Order;

use App\Exceptions\InsufficientStockException;
use App\Helpers\ConcurrencyRetry;
use App\Models\Entities\Country;
use App\Models\Entities\Orders;
use App\Models\Entities\OrdersProduct;
use App\Models\Entities\OrdersProductOption;
use App\Models\Entities\OrdersTotal;
use App\Models\Entities\Product;
use App\Models\Entities\ProductVariant;
use App\Models\Entities\ProductVariantDiscount;
use App\Repositories\Interfaces\DistrictRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\WardRepositoryInterface;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use App\Services\Checkout\ShippingFeeService;
use App\Services\Measurement\LengthService;
use App\Services\Measurement\WeightService;
use App\Services\Stock\StockService;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Tạo/sửa order TỪ CMS (staff nhập tay) — khác `CreateOrderService` (dùng cho
 * storefront checkout, phụ thuộc session cart + khách đang đăng nhập).
 *
 * Đặt ở app/Services/Order (domain), KHÔNG phải app/Services/Cms/ — service
 * này chạm quy tắc nghiệp vụ thật (giá, kho), xem
 * app/Http/Controllers/Api/Cms/README.md mục "Service chỉ CMS dùng thì đặt đâu".
 *
 * Đã làm (bám mt219 order/form.vue + subForm, đổi sang schema variant MỚI):
 *   - Khách hàng, địa chỉ (zone/district/ward — KHÔNG còn chọn country, hệ
 *     thống hiện chỉ 1 country mặc định — xem getCoreConfig('zones.country_id_default')).
 *   - Sản phẩm: giá LUÔN resolve lại từ DB theo product_id + option_value_ids
 *     + quantity — KHÔNG tin giá do FE gửi. Thuật toán mirror đúng
 *     CartService::resolvePrice()/bestDiscountTier() (2 nơi cố ý trùng logic,
 *     sửa 1 chỗ nhớ sửa chỗ kia — CartService thì session-bound nên không tái
 *     dùng thẳng được).
 *   - Phí ship qua ShippingFeeService::calculate() (dùng chung storefront).
 *   - Trừ kho qua StockService::deductForOrder() — CHỈ khi tạo đơn mới.
 *
 * Cố ý CHƯA làm (ghi rõ để không ai tưởng nhầm là bug sót):
 *   - Coupon/voucher/reward: mt219 có ở bước Confirm nhưng logic giảm giá thật
 *     nằm ở PromotionService (session/customer-bound, viết cho checkout).
 *     Thà bỏ hẳn input còn hơn hiện ô nhập mà không trừ tiền thật — dễ gây
 *     hiểu nhầm cho nhân viên. Làm riêng đợt sau nếu nghiệp vụ cần.
 *   - Sửa đơn đã tồn tại mà đổi danh sách sản phẩm: KHÔNG trừ/hoàn kho lại
 *     (kho đã trừ đúng 1 lần lúc tạo đơn). StockService chưa có API "restock"
 *     dùng chung an toàn — tự viết riêng ở đây rủi ro cao hơn lợi ích.
 *   - Giá đặc biệt (product_variant_special) lấy theo getUserGroupId() của
 *     STAFF đang đăng nhập (mặc định hệ thống), KHÔNG theo nhóm khách hàng
 *     đang lên đơn — đơn giản hoá có chủ đích, khác biệt hiếm khi phát sinh
 *     (special theo user_group là tính năng ít dùng).
 */
class OrderAdminWriteService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected StockService $stockService,
        protected ShippingFeeService $shippingFeeService,
        protected ZoneRepositoryInterface $zoneRepo,
        protected DistrictRepositoryInterface $districtRepo,
        protected WardRepositoryInterface $wardRepo,
        protected LengthService $lengthService,
        protected WeightService $weightService,
    ) {
    }

    /**
     * @param  array<string,mixed>  $data  đã qua OrderRequest::validated()
     * @return array{order: Orders, shipping_fee_ok: bool}
     */
    public function save(?Orders $existing, array $data, ?int $actorUserId): array
    {
        $isNew = $existing === null;
        $shippingFeeOk = true;

        $order = ConcurrencyRetry::run(fn () => $this->orderRepo->transaction(function () use ($existing, $data, $actorUserId, $isNew, &$shippingFeeOk) {
            $hasProducts = array_key_exists('products', $data);
            $lines = [];
            $totalsRows = [];
            $total = $existing?->total ?? 0;

            if ($hasProducts) {
                $computed = $this->computeLinesAndTotals($data);
                $lines = $computed['lines'];
                $totalsRows = $computed['totals_rows'];
                $total = $computed['total'];
                $shippingFeeOk = $computed['shipping_fee_ok'];
            }

            $orderRow = $this->buildOrderRow($existing, $data, (int) $total);
            $order = $this->orderRepo->upsertOrder($orderRow);

            $newStatusId = (int) $data['order_status_id'];
            $statusChanged = $isNew || ((int) $existing->order_status_id !== $newStatusId);
            if ($statusChanged) {
                $this->orderRepo->appendHistory(
                    $order->id,
                    $newStatusId,
                    $actorUserId,
                    (string) ($data['note'] ?? ''),
                    (bool) ($data['send_mail'] ?? false),
                );
            }

            if ($hasProducts) {
                // Thay toàn bộ dòng sản phẩm/tổng — giống mt219 gốc (xoá rồi tạo
                // lại thay vì diff từng dòng). OrdersProduct::$destroyRelations
                // xoá kèm orders_product_option nhưng đó là cascade lúc model
                // delete() qua Eloquent event — delete() hàng loạt bằng where()
                // KHÔNG fire model event, nên phải tự xoá option trước (an toàn,
                // không phụ thuộc side-effect ẩn).
                OrdersProductOption::whereIn(
                    'order_product_id',
                    OrdersProduct::where('order_id', $order->id)->pluck('id')
                )->delete();
                OrdersProduct::where('order_id', $order->id)->delete();
                OrdersTotal::where('order_id', $order->id)->delete();

                foreach ($lines as $line) {
                    $this->orderRepo->createOrderItem(
                        [
                            'order_id'           => $order->id,
                            'product_id'         => $line['product_id'],
                            'product_variant_id' => $line['product_variant_id'],
                            'name'               => $line['name'],
                            'model'              => $line['model'],
                            'quantity'           => $line['quantity'],
                            'price'              => $line['price'],
                            'total'              => $line['total'],
                            'reward'             => 0,
                        ],
                        $line['options'],
                    );
                }
                foreach ($totalsRows as $i => $row) {
                    $this->orderRepo->createOrderTotal([
                        'order_id'   => $order->id,
                        'code'       => $row['code'],
                        'title'      => $row['title'],
                        'value'      => $row['value'],
                        'sort_order' => $i,
                    ]);
                }

                if ($isNew) {
                    foreach ($lines as $line) {
                        if ((int) $line['product_variant_id'] > 0) {
                            $this->stockService->deductForOrder(
                                [
                                    'id'                 => $line['product_id'],
                                    'product_variant_id' => $line['product_variant_id'],
                                    'quantity'           => $line['quantity'],
                                    'order_id'           => $order->id,
                                ],
                                'cms:order:' . $order->id,
                                $actorUserId,
                            );
                        }
                    }
                }
            }

            return $order;
        }));

        return ['order' => $order, 'shipping_fee_ok' => $shippingFeeOk];
    }

    /**
     * Xem trước tổng tiền (tab Confirm của wizard, hoặc mỗi lần đổi hãng vận
     * chuyển) — CHẠY NGOÀI transaction, KHÔNG ghi DB, KHÔNG trừ kho. Trả cả
     * dòng sản phẩm đã tính giá để FE hiển thị bảng tạm tính trước khi bấm Save.
     *
     * @param  array<string,mixed>  $data  tối thiểu: products[], zone_id,
     *     district_id, ward_id, address, carrier_code
     */
    public function previewTotal(array $data): array
    {
        return $this->computeLinesAndTotals($data);
    }

    /**
     * @return array{lines:array,totals_rows:array,total:int,shipping_fee_ok:bool}
     */
    protected function computeLinesAndTotals(array $data): array
    {
        $lines = [];
        foreach ((array) ($data['products'] ?? []) as $raw) {
            $lines[] = $this->resolveLine($raw);
        }
        if (empty($lines)) {
            throw new RuntimeException('Đơn hàng cần ít nhất 1 sản phẩm.');
        }

        $subtotal = (int) array_sum(array_column($lines, 'total'));
        $shippingDims = $this->aggregateShipping($lines);
        $address = [
            'zone_id'       => (int) ($data['zone_id'] ?? 0),
            'district_id'   => (int) ($data['district_id'] ?? 0),
            'ward_id'       => (int) ($data['ward_id'] ?? 0),
            'zone_name'     => $this->zoneRepo->nameById((int) ($data['zone_id'] ?? 0)),
            'district_name' => $this->districtRepo->nameById((int) ($data['district_id'] ?? 0)),
            'ward_name'     => $this->wardRepo->nameById((int) ($data['ward_id'] ?? 0)),
            'address'       => (string) ($data['address'] ?? ''),
        ];

        [$feeOk, $fee] = $this->shippingFeeService->calculate(
            (string) ($data['carrier_code'] ?? ''),
            $subtotal,
            $shippingDims,
            $address,
        );
        $fee = $feeOk ? (int) $fee : 0;
        $total = $subtotal + $fee;

        return [
            'lines' => $lines,
            'totals_rows' => [
                ['code' => 'sub_total', 'title' => 'Tạm tính', 'value' => $subtotal],
                ['code' => (string) ($data['carrier_code'] ?: 'shipping'), 'title' => 'Phí vận chuyển', 'value' => $fee],
                ['code' => 'total', 'title' => 'Tổng cộng', 'value' => $total],
            ],
            'total' => $total,
            'shipping_fee_ok' => $feeOk,
        ];
    }

    /**
     * Resolve 1 dòng sản phẩm gửi từ FE ({product_id, quantity,
     * option_value_ids:[{option_id,value_id}], custom_options:[{option_id,value}]})
     * thành dòng đã có giá THẬT + option đã ghi tên/giá trị hiển thị.
     *
     * @return array{product_id:int,product_variant_id:int,model:string,name:string,
     *     quantity:int,price:int,total:int,options:array,shipping:int,weight:float,
     *     length:float,width:float,height:float}
     */
    protected function resolveLine(array $raw): array
    {
        $productId = (int) ($raw['product_id'] ?? 0);
        $quantity = max(1, (int) ($raw['quantity'] ?? 1));
        if ($productId <= 0) {
            throw new RuntimeException('Thiếu product_id cho 1 dòng sản phẩm.');
        }

        $product = Product::query()
            ->with([
                'description',
                'productOptions.option.description',
                'productVariants.productStocks',
                'productVariants.productVariantSpecial',
                'productVariants.productVariantDiscounts',
                'productVariants.productVariantAttributes.option.description',
                'productVariants.productVariantAttributes.optionValue.description',
            ])
            ->dateAvailable()
            ->find($productId);

        if (! $product) {
            throw new RuntimeException("Sản phẩm #{$productId} không tồn tại hoặc đã ẩn.");
        }

        $optionValueIds = collect((array) ($raw['option_value_ids'] ?? []))
            ->mapWithKeys(fn ($v) => [(int) ($v['option_id'] ?? 0) => (int) ($v['value_id'] ?? 0)])
            ->filter(fn ($v, $k) => $k > 0 && $v > 0)
            ->all();

        $variant = null;
        if (! empty($optionValueIds)) {
            $signature = ProductVariant::buildAttributeSignature($optionValueIds);
            $variant = $product->productVariants->firstWhere('attribute_signature', $signature);
            if (! $variant) {
                throw new RuntimeException('Sản phẩm "' . ($product->description?->name ?? $product->model) . '": biến thể đã chọn không tồn tại.');
            }
        } elseif ($existingVariantId = (int) ($raw['product_variant_id'] ?? 0)) {
            // Dòng sản phẩm ĐÃ tồn tại (sửa order cũ) mà FE không dựng lại được
            // option_value_ids — ví dụ order tạo trước khi orders_product_option
            // có row type='variant' (xem OrderData::fromModel()), hoặc bất kỳ lý
            // do nào khác khiến options[] rỗng/thiếu. TRƯỚC ĐÂY rơi thẳng xuống
            // nhánh "variant mặc định" bên dưới → âm thầm đổi nhầm giá/biến thể
            // dù nhân viên chỉ sửa tab Customer (bug phát hiện 2026-08-03 lúc
            // verify $fillable, xem docs/SCHEMA-CACHE-FILLABLE.md). Ưu tiên giữ
            // NGUYÊN variant đang lưu trong trường hợp này thay vì đoán mặc định.
            $variant = $product->productVariants->firstWhere('id', $existingVariantId);
            if (! $variant) {
                throw new RuntimeException('Sản phẩm "' . ($product->description?->name ?? $product->model) . '": biến thể đã lưu (#' . $existingVariantId . ') không còn tồn tại.');
            }
        } else {
            $variant = $product->productVariants->firstWhere('is_default', true)
                ?? $product->productVariants->first();
        }

        if (! $variant) {
            throw new RuntimeException('Sản phẩm "' . ($product->description?->name ?? $product->model) . '" chưa có biến thể/giá bán.');
        }

        if (getConfigDb('config_stock_checkout') && ! $variant->canSellQuantity($quantity)) {
            throw new InsufficientStockException((int) $variant->id, $quantity, $variant->sellableQuantityTotal());
        }

        $price = $this->resolvePrice($variant, $quantity);
        $optionRows = $this->buildOptionRows($product, $variant, (array) ($raw['custom_options'] ?? []));
        $variantLabel = trim(implode(' / ', array_filter(array_map(
            fn ($r) => $r['type'] === 'variant' ? $r['value'] : null,
            $optionRows
        ))));

        $weightClassId = (int) ($product->weight_class_id ?: getConfigDb('config_weight_class_id'));
        $lengthClassId = (int) ($product->length_class_id ?: getConfigDb('config_length_class_id'));

        return [
            'product_id'         => $product->id,
            'product_variant_id' => (int) $variant->id,
            'model'              => (string) $product->model,
            'name'               => (string) ($product->description?->name ?? '') . ($variantLabel !== '' ? " ({$variantLabel})" : ''),
            'quantity'           => $quantity,
            'price'              => $price,
            'total'              => $price * $quantity,
            'options'            => $optionRows,
            'shipping'           => (int) $product->shipping,
            'weight'             => $this->weightService->convertToSystem((float) ($product->weight ?? 0), $weightClassId) * $quantity,
            'length'             => $this->lengthService->convertToSystem((float) ($product->length ?? 0), $lengthClassId),
            'width'              => $this->lengthService->convertToSystem((float) ($product->width ?? 0), $lengthClassId),
            'height'             => $this->lengthService->convertToSystem((float) ($product->height ?? 0), $lengthClassId),
        ];
    }

    /**
     * Giá cuối = MIN(giá variant, special đang active, tier chiết khấu theo
     * số lượng đạt ngưỡng) — mirror CartService::resolvePrice()/bestDiscountTier().
     */
    protected function resolvePrice(ProductVariant $variant, int $quantity): int
    {
        $candidates = [(float) $variant->price];

        if ($special = $variant->productVariantSpecial) {
            $candidates[] = (float) $special->price;
        }

        if ($tier = $this->bestDiscountTier($variant->productVariantDiscounts ?? collect(), $quantity)) {
            $candidates[] = (float) $tier->price;
        }

        return (int) min($candidates);
    }

    protected function bestDiscountTier(Collection $discounts, int $quantity): ?ProductVariantDiscount
    {
        $best = null;
        foreach ($discounts as $tier) {
            if ((int) $tier->quantity > $quantity) {
                continue;
            }
            if (
                $best === null
                || $tier->quantity > $best->quantity
                || ($tier->quantity === $best->quantity && $tier->priority > $best->priority)
            ) {
                $best = $tier;
            }
        }

        return $best;
    }

    /**
     * Dòng option để ghi orders_product_option — 2 nguồn:
     *   - role=variant: đọc thẳng từ variant vừa resolve (KHÔNG tin lại
     *     option_value_ids FE gửi, tránh lệch tên/giá trị hiển thị).
     *   - role=custom_field: text staff nhập (Option.jsx MỚI chỉ có 1 input
     *     text + required, không còn nhiều "type" như mt219 cũ).
     *
     * @return array<int,array<string,mixed>> đã đúng shape cột orders_product_option
     */
    protected function buildOptionRows(Product $product, ProductVariant $variant, array $customOptionsRaw): array
    {
        $rows = [];

        foreach ($variant->productVariantAttributes as $attr) {
            $rows[] = [
                'product_option_id'       => $attr->option_id,
                'product_option_value_id' => $attr->option_value_id,
                'image'                   => $attr->optionValue?->image ?? '',
                'name'                    => $attr->option?->description?->name ?? '',
                'value'                   => $attr->optionValue?->description?->name ?? '',
                'type'                    => 'variant',
                'variation'               => 2,
                'required'                => 1,
                'children'                => serialize([]),
            ];
        }

        $customFieldPO = $product->productOptions->filter(
            fn ($po) => $po->option && $po->option->isCustomField()
        );
        foreach ($customFieldPO as $po) {
            $typed = collect($customOptionsRaw)->first(fn ($c) => (int) ($c['option_id'] ?? 0) === (int) $po->option_id);
            $value = trim((string) ($typed['value'] ?? $po->value ?? ''));
            if ($value === '') {
                if ($po->required) {
                    throw new RuntimeException('Vui lòng nhập đầy đủ thông tin bắt buộc cho sản phẩm "' . ($product->description?->name ?? $product->model) . '".');
                }
                continue;
            }
            $rows[] = [
                'product_option_id'       => $po->option_id,
                'product_option_value_id' => 0,
                'image'                   => '',
                'name'                    => $po->option?->description?->name ?? '',
                'value'                   => $value,
                'type'                    => 'custom_field',
                'variation'               => 2,
                'required'                => (int) $po->required,
                'children'                => serialize([]),
            ];
        }

        return $rows;
    }

    protected function aggregateShipping(array $lines): array
    {
        $shipping = ['width' => 0.0, 'height' => 0.0, 'length' => 0.0, 'weight' => 0.0];
        foreach ($lines as $line) {
            if (! $line['shipping']) {
                continue;
            }
            $shipping['width'] = max($shipping['width'], $line['width']);
            $shipping['height'] = max($shipping['height'], $line['height']);
            $shipping['length'] = max($shipping['length'], $line['length']);
            $shipping['weight'] += $line['weight'];
        }

        return $shipping;
    }

    protected function geoName(callable $resolver, mixed $id, string $fallback = ''): string
    {
        $id = (int) ($id ?? 0);
        if ($id <= 0) {
            return $fallback;
        }
        $name = (string) $resolver($id);

        return $name !== '' ? $name : $fallback;
    }

    protected function buildOrderRow(?Orders $existing, array $data, int $total): array
    {
        $countryId = (int) getCoreConfig('zones.country_id_default');
        $countryName = $countryId ? (string) (Country::find($countryId)?->name ?? '') : '';

        return [
            'id'              => $existing?->id ?? 0,
            'invoice_no'      => $existing?->invoice_no ?? strtoupper(uniqid()),
            'invoice_prefix'  => $existing?->invoice_prefix ?? (string) getConfigDb('config_invoice_prefix'),
            'user_id'         => $data['user_id'] ?? null,
            'user_address_id' => $data['user_address_id'] ?? null,
            'full_name'       => (string) $data['full_name'],
            'email'           => (string) ($data['email'] ?? ''),
            'telephone'       => (string) $data['telephone'],
            'address'         => (string) $data['address'],
            'country'         => $countryName,
            'country_id'      => $countryId,
            'zone'            => $this->geoName($this->zoneRepo->nameById(...), $data['zone_id'] ?? null),
            'zone_id'         => (int) ($data['zone_id'] ?? 0),
            'district'        => $this->geoName($this->districtRepo->nameById(...), $data['district_id'] ?? null),
            'district_id'     => (int) ($data['district_id'] ?? 0),
            'ward'            => $this->geoName($this->wardRepo->nameById(...), $data['ward_id'] ?? null),
            'ward_id'         => (int) ($data['ward_id'] ?? 0),
            // order/view.jsx (đổi trạng thái, KHÔNG cho sửa vận chuyển/thanh
            // toán) không gửi 2 field này — giữ nguyên giá trị cũ, tránh bị
            // ghi đè rỗng. order/form.jsx (wizard, tab Confirm) luôn gửi.
            'payment_code'    => (string) ($data['payment_code'] ?? $existing?->payment_code ?? ''),
            'carrier_code'    => (string) ($data['carrier_code'] ?? $existing?->carrier_code ?? ''),
            'comment'         => (string) ($data['comment'] ?? ''),
            'total'           => $total,
            'order_status_id' => (int) $data['order_status_id'],
            'language_code'   => app()->getLocale(),
            'currency_code'   => 'VND',
            'currency_value'  => 1,
            'is_read'         => 1,
            'ip'              => request()->server('REMOTE_ADDR', ''),
            'forwarded_ip'    => getForwardedIp(),
            'user_agent'      => request()->server('HTTP_USER_AGENT', ''),
            'accept_language' => request()->server('HTTP_ACCEPT_LANGUAGE', ''),
        ];
    }
}
