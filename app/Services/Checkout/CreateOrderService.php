<?php

namespace App\Services\Checkout;

use App\Models\Entities\CouponHistory;
use App\Models\Entities\OrdersProduct;
use App\Models\Entities\OrdersProductOption;
use App\Models\Entities\OrdersTotal;
use App\Models\Entities\Product;
use App\Models\Entities\ProductStock;
use App\Models\Entities\VoucherHistory;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Tạo order + ghi sub-tables (orders_product, orders_product_option,
 * orders_total, coupon_history, voucher_history, user_reward) trong 1 transaction.
 *
 * Port logic từ trait CreateOrder cũ — KHÔNG bao DB::beginTransaction() lồng
 * nhau như trait cũ. 1 transaction duy nhất bao toàn bộ. Caller chỉ cần gọi
 * `create($ctx, $params, $totalData)` rồi đọc `$ctx->orderId`.
 *
 * Subtract stock: cluster variant mới có ProductStock — trừ on_hand qua
 * UPDATE atomic (giữ optimistic lock đơn giản; refactor sau với StockService
 * khi cần concurrency cao). Simple product (has_variants=false) trừ
 * product.quantity như cũ.
 */
class CreateOrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected UserRewardRepositoryInterface $rewardRepo,
    ) {
    }

    public function create(CheckoutContext $ctx, array $params, array $totalData, int $total): int
    {
        return DB::transaction(function () use ($ctx, $params, $totalData, $total) {
            $uniqid = strtoupper(uniqid());

            $order = $this->orderRepo->upsertOrder($this->buildOrderRow($ctx, $params, $total, $uniqid));
            $ctx->setOrderId($order->id);

            $this->orderRepo->appendHistory($order->id, (int) getConfigDb('order_status_id'));

            $this->writeOrderItems($ctx, $order->id);
            $this->writeOrderTotals($order->id, $totalData);
            $this->writeCouponHistory($ctx);
            $this->writeVoucherHistory($ctx, $totalData);
            $this->writeUserReward($ctx);

            return $order->id;
        });
    }

    protected function buildOrderRow(CheckoutContext $ctx, array $params, int $total, string $uniqid): array
    {
        $shipping = (array) session()->get('cart_shipping', []);

        return [
            'id'                => (int) ($params['id'] ?? 0),
            'invoice_no'        => $uniqid,
            'invoice_prefix'    => getConfigDb('config_invoice_prefix'),
            'user_id'           => getCurrentUserId(),
            'user_address_id'   => $params['user_address_id'] ?? null,
            'full_name'         => $params['full_name'] ?? '',
            'email'             => $params['email'] ?? '',
            'telephone'         => $params['telephone'] ?? '',
            'address'           => $params['address'] ?? '',
            'country_id'        => 230,
            'zone'              => $params['zone_name'] ?? '',
            'zone_id'           => $params['zone_id'] ?? null,
            'district'          => $params['district_name'] ?? '',
            'district_id'       => $params['district_id'] ?? null,
            'ward'              => $params['ward_name'] ?? '',
            'ward_id'           => $params['ward_id'] ?? null,
            'payment_code'      => $params['payment_code'] ?? '',
            'carrier_code'      => $params['carrier_code'] ?? '',
            'comment'           => $params['comment'] ?? '',
            'voucher'           => $ctx->voucher['code'] ?? null,
            'coupon'            => $ctx->coupon['code'] ?? null,
            'reward'            => null,
            'width_class_id'    => getConfigDb('config_length_class_id'),
            'width'             => $shipping['width'] ?? 0,
            'height'            => $shipping['height'] ?? 0,
            'length'            => $shipping['length'] ?? 0,
            'weight_class_id'   => getConfigDb('config_weight_class_id'),
            'weight'            => $shipping['weight'] ?? 0,
            'total'             => $total,
            'currency_code'     => 'VND',
            'language_code'     => app()->getLocale(),
            'order_status_id'   => getConfigDb('order_status_id'),
            'user_agent'        => request()->server('HTTP_USER_AGENT', ''),
            'forwarded_ip'      => getForwardedIp(),
            'accept_language'   => request()->server('HTTP_ACCEPT_LANGUAGE', ''),
            'ip'                => request()->server('REMOTE_ADDR'),
        ];
    }

    /**
     * Ghi orders_product + orders_product_option cho từng item. Trừ tồn theo
     * 2 nhánh: variant → product_stock, simple → product.quantity.
     */
    protected function writeOrderItems(CheckoutContext $ctx, int $orderId): void
    {
        foreach ($ctx->items as $item) {
            $this->subtractStock($item);

            // Cluster variant: cột product_variant_id link 1 row order ↔ 1
            // variant cụ thể (migration 2026_05_31_000000). Cho phép NULL với
            // simple product (has_variants = false) và row legacy trước migrate.
            $orderProduct = OrdersProduct::create([
                'order_id'           => $orderId,
                'product_id'         => $item['id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'name'               => $item['name'].($item['variant_label'] ? ' ('.$item['variant_label'].')' : ''),
                'model'              => $item['model'] ?? '',
                'quantity'           => $item['quantity'],
                'price'              => $item['price'],
                'total'              => $item['total'],
                'reward'             => $item['reward'] ?? 0,
            ]);

            foreach ((array) ($item['option'] ?? []) as $opt) {
                // Schema `orders_product_option` thực tế KHÔNG có cột
                // `product_id` (đã verify trên DB live) — trait CreateOrder
                // cũ ghi sai sẽ throw "Unknown column". Chỉ ghi `order_product_id`.
                OrdersProductOption::create([
                    'order_id'                => $orderId,
                    'order_product_id'        => $orderProduct->id,
                    'product_option_id'       => $opt['product_option_id'] ?? null,
                    'product_option_value_id' => $opt['product_option_value_id'] ?? null,
                    'image'                   => $opt['image'] ?? '',
                    'name'                    => $opt['name'] ?? '',
                    'value'                   => $opt['value'] ?? '',
                    'type'                    => $opt['type'] ?? '',
                    'variation'               => $opt['variation'] ?? 2,
                    'required'                => $opt['required'] ?? 0,
                    'children'                => serialize($opt['child'] ?? []),
                ]);
            }
        }
    }

    protected function subtractStock(array $item): void
    {
        $variantId = $item['product_variant_id'] ?? null;
        $qty = (int) $item['quantity'];

        if ($variantId) {
            ProductStock::where('product_variant_id', $variantId)
                ->where('warehouse_id', ProductStock::DEFAULT_WAREHOUSE_ID)
                ->update(['on_hand' => DB::raw('on_hand - '.$qty)]);

            return;
        }

        Product::where('id', $item['id'])
            ->where('subtract', 1)
            ->update(['quantity' => DB::raw('quantity - '.$qty)]);
    }

    protected function writeOrderTotals(int $orderId, array $totalData): void
    {
        foreach ($totalData as $i => $row) {
            OrdersTotal::create([
                'order_id'   => $orderId,
                'code'       => $row['code'],
                'title'      => $row['title'],
                'value'      => $row['value'],
                'sort_order' => $i,
            ]);
        }
    }

    protected function writeCouponHistory(CheckoutContext $ctx): void
    {
        if (empty($ctx->coupon) || ! $ctx->orderId) {
            return;
        }
        CouponHistory::create([
            'coupon_id' => $ctx->coupon['coupon_id'],
            'order_id'  => $ctx->orderId,
            'amount'    => $ctx->coupon['discount'],
        ]);
    }

    protected function writeVoucherHistory(CheckoutContext $ctx, array $totalData): void
    {
        if (empty($ctx->voucher) || ! $ctx->orderId) {
            return;
        }
        $voucherLine = collect($totalData)->firstWhere('code', 'voucher');
        if (! $voucherLine) {
            return;
        }
        VoucherHistory::create([
            'voucher_id' => $ctx->voucher['id'],
            'order_id'   => $ctx->orderId,
            'amount'     => $voucherLine['value'],
        ]);
    }

    protected function writeUserReward(CheckoutContext $ctx): void
    {
        if (! auth()->check() || ! $ctx->orderId) {
            return;
        }
        $points = (int) array_sum(array_column($ctx->items, 'reward'));
        $this->rewardRepo->recordOrderReward((int) auth()->id(), $ctx->orderId, $points);
    }
}
