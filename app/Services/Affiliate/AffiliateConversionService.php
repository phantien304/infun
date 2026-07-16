<?php

namespace App\Services\Affiliate;

use App\Data\Affiliate\AffiliateAttribution;
use App\Data\Affiliate\AffiliateConversionData;
use App\Models\Entities\AffiliateCommissionRule;
use App\Models\Entities\Orders;
use App\Models\Entities\ProductCategory;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;

class AffiliateConversionService
{
    public function __construct(
        protected AffiliateAttributionService $attribution,
        protected AffiliateConversionRepositoryInterface $conversionRepo,
    ) {
    }

    public function record(int $orderId, array $items, array $totalData): void
    {
        if ($orderId <= 0 || ! $this->attribution->enabled()) {
            return;
        }

        $attr = $this->attribution->resolve();
        if (! $attr) {
            return;
        }

        $base = $this->discountedBase($totalData);
        if ($base <= 0) {
            return;
        }

        [$commission, $rate] = $this->computeCommission($items, $base, $attr);
        if ($commission <= 0) {
            return;
        }

        $this->conversionRepo->recordConversion(new AffiliateConversionData(
            affiliateId: $attr->affiliateId,
            orderId: $orderId,
            orderTotal: $base,
            commission: $commission,
            rate: $rate,
            clickId: $attr->clickId,
            couponCode: $attr->couponCode,
        ));

        Orders::query()->where('id', $orderId)->update(['affiliate_id' => $attr->affiliateId]);
    }

    protected function discountedBase(array $totalData): int
    {
        $base = 0;
        foreach ($totalData as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === 'sub_total'
                || $code === 'reward'
                || str_starts_with($code, 'coupon:')
                || str_starts_with($code, 'voucher:')) {
                $base += (int) ($row['value'] ?? 0);
            }
        }

        return max(0, $base);
    }

    protected function computeCommission(array $items, int $base, AffiliateAttribution $attr): array
    {
        $subtotal = (int) array_sum(array_column($items, 'total'));
        if ($subtotal <= 0) {
            return [0, 0.0];
        }
        $factor = $base / $subtotal;

        $categoryRates = $attr->commissionRate === null
            ? $this->categoryRates(array_column($items, 'id'))
            : [];
        $globalRate = (float) getConfigDb('config_affiliate_commission_rate', 0);

        $commission = 0;
        foreach ($items as $item) {
            $rate = $attr->commissionRate
                ?? $categoryRates[(int) ($item['id'] ?? 0)]
                ?? $globalRate;
            if ($rate <= 0) {
                continue;
            }
            $commission += (int) round((int) $item['total'] * $factor * $rate / 100);
        }

        $effectiveRate = $base > 0 ? round($commission / $base * 100, 2) : 0.0;

        return [$commission, $effectiveRate];
    }

    protected function categoryRates(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return [];
        }

        $links = ProductCategory::whereIn('product_id', $productIds)
            ->get(['product_id', 'category_id']);
        if ($links->isEmpty()) {
            return [];
        }

        $rules = AffiliateCommissionRule::whereIn(
            'category_id',
            $links->pluck('category_id')->unique()->all(),
        )->pluck('rate', 'category_id');
        if ($rules->isEmpty()) {
            return [];
        }

        $map = [];
        foreach ($links as $link) {
            $rate = $rules->get($link->category_id);
            if ($rate === null) {
                continue;
            }
            $pid = (int) $link->product_id;
            $map[$pid] = max((float) $rate, $map[$pid] ?? 0.0);
        }

        return $map;
    }
}
