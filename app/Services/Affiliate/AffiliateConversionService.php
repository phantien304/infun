<?php

namespace App\Services\Affiliate;

use App\Data\Affiliate\AffiliateAttribution;
use App\Data\Affiliate\AffiliateConversionData;
use App\Models\Entities\AffiliateCommissionRule;
use App\Models\Entities\Orders;
use App\Models\Entities\ProductCategory;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;

/**
 * Ghi hoa hồng khi tạo đơn (Phase 3 — AFFILIATE-PLAN.md).
 *
 * Business đã chốt:
 * - Base tính hoa hồng = SAU discount (coupon/reward/voucher), TRƯỚC ship —
 *   đọc từ totalData thay vì tự cộng lại, để luôn khớp số trên orders_total.
 *   Discount phân bổ tỷ lệ vào từng item (factor = base / subtotal).
 * - Rate precedence THEO TỪNG ITEM: affiliate.commission_rate (per-KOL,
 *   flat cho cả đơn) > affiliate_commission_rule theo category của SP
 *   (nhiều category → lấy rate cao nhất) > config_affiliate_commission_rate.
 * - Row conversion PENDING; observer approve khi giao / reject khi hủy.
 *
 * Lỗi ở đây KHÔNG được phá flow đặt hàng — caller wrap try/catch.
 */
class AffiliateConversionService
{
    public function __construct(
        protected AffiliateAttributionService $attribution,
        protected AffiliateConversionRepositoryInterface $conversionRepo,
    ) {
    }

    /**
     * @param array $items     cart items (CheckoutPromotions->items)
     * @param array $totalData các dòng totals đã build (CheckoutTotalService)
     */
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

        // Query-builder update: không fire model events (không kích observer).
        Orders::query()->where('id', $orderId)->update(['affiliate_id' => $attr->affiliateId]);
    }

    /**
     * Base = sub_total + các dòng giảm (coupon:/reward/voucher:) — âm sẵn.
     * KHÔNG gồm ship, coupon_freeship (giảm phí ship), gifts (value 0), total.
     */
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

    /** @return array{0:int,1:float} [tiền hoa hồng, rate hiệu dụng % để audit] */
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

    /**
     * Map product_id → rate theo affiliate_commission_rule. SP thuộc nhiều
     * category có rule → lấy rate CAO NHẤT (deterministic, có lợi cho KOL).
     */
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
