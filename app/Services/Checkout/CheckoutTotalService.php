<?php

namespace App\Services\Checkout;

use App\Repositories\Interfaces\UserRewardRepositoryInterface;

class CheckoutTotalService
{
    public function __construct(
        protected ShippingFeeService $shippingFee,
        protected UserRewardRepositoryInterface $rewardRepo,
        protected PromotionService $promotionService,
    ) {
    }

    public function build(CheckoutPromotions $promotions, bool $withShipping = true): array
    {
        $totalData = [];
        $totalPrice = (int) array_sum(array_column($promotions->items, 'total'));

        $this->lineSubTotal($totalData, $totalPrice);
        $this->linesAppliedCoupons($promotions, $totalData, $totalPrice);
        $this->lineGifts($totalData);
        $this->lineReward($promotions, $totalData, $totalPrice);
        if ($withShipping) {
            $this->lineShipping($promotions, $totalData, $totalPrice);
        }
        $this->lineVouchers($totalData, $totalPrice);
        $this->lineTotal($totalData, $totalPrice);

        return [$totalData, max(0, $totalPrice)];
    }

    protected function lineSubTotal(array &$totalData, int &$total): void
    {
        $totalData[] = [
            'code'  => 'sub_total',
            'title' => trans('messages.TextSubTotal'),
            'text'  => $this->money($total),
            'value' => $total,
        ];
    }

    protected function linesAppliedCoupons(CheckoutPromotions $promotions, array &$totalData, int &$total): void
    {
        $typeFreeship = (int) getCoreConfig('coupon.type.freeship');

        foreach ($promotions->appliedCoupons as $entry) {
            $coupon = $entry['coupon'];
            $type = (int) ($entry['type'] ?? $coupon->type);
            if ($type === $typeFreeship) {
                continue;
            }

            $discount = max(0, min((int) $entry['discount'], $total));
            if ($discount <= 0) {
                continue;
            }

            $totalData[] = [
                'code'  => 'coupon:' . $coupon->code,
                'title' => sprintf(trans('messages.TextCoupon'), $coupon->code),
                'text'  => '-' . $this->money($discount),
                'value' => -$discount,
            ];
            $total -= $discount;
        }
    }

    protected function lineGifts(array &$totalData): void
    {
        $applied = (array) session()->get(getCoreConfig('session.applied_gifts'), []);
        $count = 0;
        foreach ($applied as $entry) {
            $count += count((array) ($entry['item_ids'] ?? []));
        }
        if ($count <= 0) {
            return;
        }
        $totalData[] = [
            'code'  => 'gifts',
            'title' => trans('messages.checkout.gift'),
            'text'  => sprintf(trans('messages.checkout.gift_count'), $count),
            'value' => 0,
        ];
    }
    // todo
    protected function lineReward(CheckoutPromotions $promotions, array &$totalData, int &$total): void
    {
        if (getConfigDb('config_reward_point_enabled') == setting('reward_point.disable')) {
            return;
        }
        if (! session()->has(getCoreConfig('session.reward')) || ! auth()->check()) {
            return;
        }

        $available = $this->rewardRepo->getTotalPoints((int) auth()->id());
        $reward = (int) session()->get(getCoreConfig('session.reward'));
        if ($reward <= 0 || $reward > $available) {
            return;
        }

        $pointsTotal = 0;
        foreach ($promotions->items as $item) {
            $pointsTotal += (int) ($item['points'] ?? 0);
        }
        if ($pointsTotal <= 0) {
            return;
        }

        $discountTotal = 0;
        foreach ($promotions->items as $item) {
            if (! empty($item['points'])) {
                $discountTotal += (int) ($item['total'] * ($reward / $pointsTotal));
            }
        }

        $totalData[] = [
            'code'  => 'reward',
            'title' => sprintf(trans('messages.TextReward'), $reward),
            'text'  => '-'.$this->money($discountTotal),
            'value' => -$discountTotal,
        ];
        $total -= $discountTotal;
    }

    protected function lineShipping(CheckoutPromotions $promotions, array &$totalData, int &$total): void
    {
        $carrierCode = (string) request()->get('carrier_code');

        if (! filled($carrierCode)) {
            $this->lineFreeshipPlaceholder($promotions, $totalData);
            return;
        }

        $address = $this->extractAddress();
        $cartShipping = (array) session()->get(getCoreConfig('session.cart_shipping'), []);

        [$ok, $fee] = $this->shippingFee->calculate($carrierCode, $total, $cartShipping, $address);
        if (! $ok || $fee === null) {
            $this->lineFreeshipPlaceholder($promotions, $totalData);
            return;
        }

        $totalData[] = [
            'code'  => $carrierCode,
            'title' => trans('messages.TextFeeShipping'),
            'text'  => '+'.$this->money($fee),
            'value' => $fee,
        ];
        $total += $fee;

        if ($promotions->hasFreeshipCoupon && $fee > 0) {
            $entry = $this->findFreeshipEntry($promotions);
            $shipDiscount = $this->freeshipShipDiscount($entry, $fee);
            if ($shipDiscount > 0) {
                $code = $entry['coupon']->code ?? null;
                $totalData[] = [
                    'code'  => 'coupon_freeship',
                    'title' => $code !== null
                        ? sprintf(trans('messages.TextCoupon'), $code)
                        : trans('messages.TextCoupon', ['code' => 'FREESHIP']),
                    'text'  => '-' . $this->money($shipDiscount),
                    'value' => -$shipDiscount,
                ];
                $total -= $shipDiscount;
            }
        }
    }

    protected function lineFreeshipPlaceholder(CheckoutPromotions $promotions, array &$totalData): void
    {
        $entry = $this->findFreeshipEntry($promotions);
        if ($entry === null) {
            return;
        }
        $code = $entry['coupon']->code ?? null;
        $cap = $this->freeshipCap($entry['coupon']);
        $totalData[] = [
            'code'  => 'coupon_freeship_pending',
            'title' => $code !== null
                ? sprintf(trans('messages.TextCoupon'), $code)
                : trans('messages.checkout.freeship_voucher'),
            'text'  => $cap > 0
                ? sprintf(trans('messages.checkout.freeship_cap'), $this->money($cap))
                : trans('messages.checkout.freeship_apply'),
            'value' => 0,
        ];
    }

    protected function findFreeshipEntry(CheckoutPromotions $ctx): ?array
    {
        $typeFreeship = (int) getCoreConfig('coupon.type.freeship');
        foreach ($ctx->appliedCoupons as $entry) {
            if ((int) ($entry['type'] ?? $entry['coupon']->type) === $typeFreeship) {
                return $entry;
            }
        }

        return null;
    }

    protected function freeshipCap($coupon): int
    {
        return (int) ($coupon->discount_max ?: $coupon->discount);
    }

    protected function freeshipShipDiscount(?array $entry, int $fee): int
    {
        if ($entry === null || $fee <= 0) {
            return 0;
        }
        $cap = $this->freeshipCap($entry['coupon']);

        return $cap > 0 ? min($fee, $cap) : $fee;
    }

    protected function lineVouchers(array &$totalData, int &$total): void
    {
        if ($total <= 0) {
            return;
        }
        $result = $this->promotionService->resolveVouchers($total);
        if (empty($result['applied'])) {
            return;
        }
        foreach ($result['applied'] as $entry) {
            $amount = (int) $entry['amount'];
            if ($amount <= 0) {
                continue;
            }
            $voucher = $entry['voucher'];
            $totalData[] = [
                'code'  => 'voucher:' . $voucher->code,
                'title' => sprintf(trans('messages.checkout.gift_card'), $voucher->code),
                'text'  => '-' . $this->money($amount),
                'value' => -$amount,
            ];
            $total -= $amount;
        }
    }

    protected function lineTotal(array &$totalData, int &$total): void
    {
        $total = max(0, $total);
        $totalData[] = [
            'code'  => 'total',
            'title' => trans('messages.TextTotal'),
            'text'  => $this->money($total),
            'value' => $total,
        ];
    }

    protected function extractAddress(): array
    {
        $cookie = json_decode((string) request()->cookie('address_customer'), true) ?: [];
        $default = collect($cookie)->firstWhere('is_default', 1) ?: ($cookie[0] ?? []);

        return (array) $default;
    }

    protected function money(int $amount): string
    {
        return number_format($amount, 0, '', ',').'đ';
    }
}
