<?php

namespace App\Services\Checkout;

use App\Repositories\Interfaces\DistrictRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use App\Repositories\Interfaces\WardRepositoryInterface;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use App\Services\Affiliate\AffiliateConversionService;
use App\Services\Currency\CurrencyService;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;

class CreateOrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected UserRewardRepositoryInterface $userRewardRepo,
        protected PromotionService $promotionService,
        protected StockService $stockService,
        protected CurrencyService $currencyService,
        protected AffiliateConversionService $affiliateConversion,
        protected ZoneRepositoryInterface $zoneRepo,
        protected DistrictRepositoryInterface $districtRepo,
        protected WardRepositoryInterface $wardRepo,
    ) {
    }

    public function create(CheckoutPromotions $promotions, array $params, array $totalData, int $total): int
    {
        return $this->orderRepo->transaction(function () use ($promotions, $params, $totalData, $total) {
            $uniqid = strtoupper(uniqid());

            $order = $this->orderRepo->upsertOrder($this->buildOrderRow($params, $total, $uniqid));
            $promotions->setOrderId($order->id);

            $this->orderRepo->appendHistory($order->id, (int) getConfigDb('order_status_id'));

            $this->writeOrderItems($promotions, $order->id);
            $this->writeOrderTotals($order->id, $totalData);

            $this->subtractStockForItems($promotions, $order->id);
            $this->promotionService->recordForOrder($promotions, $order->id, $total);
            $this->writeRewardRedeem($order->id, $totalData);

            DB::afterCommit(function () use ($promotions, $totalData, $order) {
                $this->writeUserReward($promotions);
                $this->writeAffiliateConversion($order->id, $promotions, $totalData);
            });

            return $order->id;
        }, attempts: 3);
    }

    protected function buildOrderRow(array $params, int $total, string $uniqid): array
    {
        $shipping = (array) session()->get(getCoreConfig('session.cart_shipping'), []);
        $currency = $this->currencyService->currentCurrency();

        return [
            'id'                => 0,
            'invoice_no'        => $uniqid,
            'invoice_prefix'    => getConfigDb('config_invoice_prefix'),
            'user_id'           => getCurrentUserId(),
            'user_address_id'   => $params['user_address_id'] ?? null,
            'full_name'         => $params['full_name'] ?? '',
            'email'             => $params['email'] ?? '',
            'telephone'         => $params['telephone'] ?? '',
            'address'           => $params['address'] ?? '',
            'country_id'        => getCoreConfig('zones.country_id_default'),
            'zone'              => $this->geoName($this->zoneRepo->nameById(...), $params['zone_id'] ?? null, (string) ($params['zone_name'] ?? '')),
            'zone_id'           => $params['zone_id'] ?? null,
            'district'          => $this->geoName($this->districtRepo->nameById(...), $params['district_id'] ?? null, (string) ($params['district_name'] ?? '')),
            'district_id'       => $params['district_id'] ?? null,
            'ward'              => $this->geoName($this->wardRepo->nameById(...), $params['ward_id'] ?? null, (string) ($params['ward_name'] ?? '')),
            'ward_id'           => $params['ward_id'] ?? null,
            'payment_code'      => $params['payment_code'] ?? '',
            'carrier_code'      => $params['carrier_code'] ?? '',
            'comment'           => $params['comment'] ?? '',
            'length_class_id'   => getConfigDb('config_length_class_id'),
            'width'             => $shipping['width'] ?? 0,
            'height'            => $shipping['height'] ?? 0,
            'length'            => $shipping['length'] ?? 0,
            'weight_class_id'   => getConfigDb('config_weight_class_id'),
            'weight'            => $shipping['weight'] ?? 0,
            'total'             => $total,
            'currency_id'       => $currency->id,
            'currency_code'     => $currency->code,
            'currency_value'    => $currency->value,
            'language_code'     => app()->getLocale(),
            'order_status_id'   => getConfigDb('order_status_id'),
            'user_agent'        => request()->server('HTTP_USER_AGENT', ''),
            'forwarded_ip'      => getForwardedIp(),
            'accept_language'   => request()->server('HTTP_ACCEPT_LANGUAGE', ''),
            'ip'                => request()->server('REMOTE_ADDR'),
        ];
    }

    protected function geoName(callable $resolver, mixed $id, string $fallback): string
    {
        $id = (int) ($id ?? 0);
        if ($id <= 0) {
            return $fallback;
        }
        $name = (string) $resolver($id);
        return $name !== '' ? $name : $fallback;
    }

    protected function writeOrderItems(CheckoutPromotions $promotions, int $orderId): void
    {
        foreach ($promotions->items as $item) {
            $this->orderRepo->createOrderItem(
                $this->buildOrderProductRow($item, $orderId),
                $this->buildOrderProductOptionRows($item),
            );
        }
    }

    protected function subtractStockForItems(CheckoutPromotions $promotions, int $orderId): void
    {
        foreach ($promotions->items as $item) {
            $this->subtractStock($item + ['order_id' => $orderId]);
        }
    }

    protected function buildOrderProductRow(array $item, int $orderId): array
    {
        return [
            'order_id'           => $orderId,
            'product_id'         => $item['id'],
            'product_variant_id' => $item['product_variant_id'] ?? null,
            'name'               => $item['name'].($item['variant_label'] ? ' ('.$item['variant_label'].')' : ''),
            'model'              => $item['model'] ?? '',
            'quantity'           => $item['quantity'],
            'price'              => $item['price'],
            'total'              => $item['total'],
            'reward'             => $item['reward'] ?? 0,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function buildOrderProductOptionRows(array $item): array
    {
        $rows = [];
        foreach ((array) ($item['option'] ?? []) as $opt) {
            $rows[] = [
                'product_option_id'       => $opt['option_id'] ?? null,
                'product_option_value_id' => $opt['product_option_value_id'] ?? null,
                'image'                   => $opt['image'] ?? '',
                'name'                    => $opt['name'] ?? '',
                'value'                   => $opt['value'] ?? '',
                'type'                    => $opt['type'] ?? '',
                'variation'               => $opt['variation'] ?? 2,
                'required'                => $opt['required'] ?? 0,
                'children'                => serialize($opt['child'] ?? []),
            ];
        }

        return $rows;
    }

    protected function subtractStock(array $item): void
    {
        $this->stockService->deductForOrder(
            $item,
            (string) session()->getId(),
            (int) getCurrentUserId() ?: null,
        );
    }

    protected function writeOrderTotals(int $orderId, array $totalData): void
    {
        foreach ($totalData as $i => $row) {
            $this->orderRepo->createOrderTotal([
                'order_id'   => $orderId,
                'code'       => $row['code'],
                'title'      => $row['title'],
                'value'      => $row['value'],
                'sort_order' => $i,
            ]);
        }
    }

    protected function writeUserReward(CheckoutPromotions $promotions): void
    {
        if (! auth()->check() || ! $promotions->orderId) {
            return;
        }

        try {
            $points = (int) array_sum(array_column($promotions->items, 'reward'));
            $this->userRewardRepo->recordOrderReward((int) getCurrentUserId(), $promotions->orderId, $points);
        } catch (\Throwable $e) {
            logError('writeUserReward: '.$e->getMessage(), ['order_id' => $promotions->orderId]);
        }
    }

    protected function writeRewardRedeem(int $orderId, array $totalData): void
    {
        if (! auth()->check()) {
            return;
        }
        foreach ($totalData as $row) {
            if (($row['code'] ?? '') === 'reward' && (int) ($row['points'] ?? 0) > 0) {
                $this->userRewardRepo->recordRedeem((int) getCurrentUserId(), $orderId, (int) $row['points']);

                return;
            }
        }
    }

    protected function writeAffiliateConversion(int $orderId, CheckoutPromotions $promotions, array $totalData): void
    {
        try {
            $this->affiliateConversion->record($orderId, $promotions->items, $totalData);
        } catch (\Throwable $e) {
            logError('writeAffiliateConversion: '.$e->getMessage(), ['order_id' => $orderId]);
        }
    }
}
