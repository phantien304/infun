<?php

namespace App\Services\Checkout;

use App\Models\Entities\OrdersProduct;
use App\Models\Entities\OrdersProductOption;
use App\Models\Entities\OrdersTotal;
use App\Models\Entities\ProductStock;
use App\Models\Entities\StockMovement;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Build an order + write its sub-tables (orders_product, orders_product_option,
 * orders_total, coupon_history, voucher_history, user_reward) in a single
 * transaction.
 *
 * Subtract stock: post unify_simple_product_stock migration every cart line
 * carries a product_variant_id (default variants are created for simple
 * products), so subtractStock takes one path through product_stock guarded by
 * inventory_policy. Variants whose policy is BACKORDER are allowed to drive
 * on_hand negative — that negative is the "bán khống" backlog admins act on.
 * A legacy fallback to product.quantity exists for cart lines that pre-date
 * the migration; it will be removed once the legacy columns are dropped.
 */
class CreateOrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected UserRewardRepositoryInterface $rewardRepo,
        protected PromotionService $promotions,
    ) {
    }

    public function create(CheckoutPromotions $ctx, array $params, array $totalData, int $total): int
    {
        return DB::transaction(function () use ($ctx, $params, $totalData, $total) {
            $uniqid = strtoupper(uniqid());

            $order = $this->orderRepo->upsertOrder($this->buildOrderRow($ctx, $params, $total, $uniqid));
            $ctx->setOrderId($order->id);

            $this->orderRepo->appendHistory($order->id, (int) getConfigDb('order_status_id'));

            $this->writeOrderItems($ctx, $order->id);
            $this->writeOrderTotals($order->id, $totalData);
            $this->promotions->recordForOrder($ctx, $order->id, $total);
            $this->writeUserReward($ctx);

            return $order->id;
        });
    }

    protected function buildOrderRow(CheckoutPromotions $ctx, array $params, int $total, string $uniqid): array
    {
        $shipping = (array) session()->get(getCoreConfig('session.cart_shipping'), []);

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
            // Cột legacy denormalized — dữ liệu KM thật nằm ở coupon_history /
            // voucher_history. Giữ cột (ghi null) để không đổi shape insert.
            'voucher'           => null,
            'coupon'            => null,
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
     * Write orders_product + orders_product_option for each line and
     * decrement stock. Post-unify the stock path is a single branch
     * through product_stock (see subtractStock); the order_id is forwarded
     * so the audit row in stock_movement can point back to the order.
     */
    protected function writeOrderItems(CheckoutPromotions $ctx, int $orderId): void
    {
        foreach ($ctx->items as $item) {
            $this->subtractStock($item + ['order_id' => $orderId]);

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
                    'product_option_id'       => $opt['option_id'] ?? null,
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

        if (! $variantId) {
            logError(sprintf(
                'subtractStock: order line for product %s has no product_variant_id; stock not decremented',
                $item['id'] ?? 'unknown',
            ));
            return;
        }

        $warehouseId = (int) getCoreConfig('stock.default_warehouse_id');

        $stock = ProductStock::where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            logError(sprintf(
                'subtractStock: no product_stock row for variant %d; stock not decremented',
                $variantId,
            ));
            return;
        }

        $policy = (int) ($stock->inventory_policy ?? getCoreConfig('stock.policy.deny'));
        if ($policy === (int) getCoreConfig('stock.policy.untracked')) {
            return;
        }

        $newOnHand = (int) ($stock->on_hand ?? 0) - $qty;
        $stock->on_hand = $newOnHand;
        $stock->version = (int) ($stock->version ?? 0) + 1;
        $stock->save();

        $isBackorder = $newOnHand < 0
            && $policy === (int) getCoreConfig('stock.policy.backorder');

        StockMovement::create([
            'product_variant_id' => $variantId,
            'warehouse_id'       => $warehouseId,
            'type'               => $isBackorder
                ? (string) getCoreConfig('stock.movement_type.sale_backorder')
                : (string) getCoreConfig('stock.movement_type.sale'),
            'quantity_change'    => -$qty,
            'on_hand_after'      => $newOnHand,
            'reference_type'     => 'order',
            'reference_id'       => $item['order_id'] ?? null,
            'user_id'            => (int) getCurrentUserId() ?: null,
            'note'               => $isBackorder ? 'Sale exceeded on_hand — backorder backlog' : null,
        ]);
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

    protected function writeUserReward(CheckoutPromotions $ctx): void
    {
        if (! auth()->check() || ! $ctx->orderId) {
            return;
        }
        $points = (int) array_sum(array_column($ctx->items, 'reward'));
        $this->rewardRepo->recordOrderReward((int) auth()->id(), $ctx->orderId, $points);
    }
}
