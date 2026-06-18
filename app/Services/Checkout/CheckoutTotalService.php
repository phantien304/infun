<?php

namespace App\Services\Checkout;

use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use App\Services\Cart\VoucherService;

class CheckoutTotalService
{
    public function __construct(
        protected ShippingFeeService $shippingFee,
        protected UserRewardRepositoryInterface $rewardRepo,
        protected VoucherService $voucherService,
    ) {
    }

    public function build(CheckoutContext $ctx, bool $withShipping = true): array
    {
        $totalData = [];
        $running = (int) array_sum(array_column($ctx->items, 'total'));

        $this->lineSubTotal($totalData, $running);
        $this->linesAppliedCoupons($ctx, $totalData, $running);
        $this->lineGifts($totalData);
        $this->lineReward($ctx, $totalData, $running);
        if ($withShipping) {
            $this->lineShipping($ctx, $totalData, $running);
        }
        $this->lineVouchers($totalData, $running);
        $this->lineTotal($totalData, $running);

        return [$totalData, max(0, $running)];
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

    protected function linesAppliedCoupons(CheckoutContext $ctx, array &$totalData, int &$total): void
    {
        $typeFreeship = (int) getCoreConfig('coupon.type.freeship');

        foreach ($ctx->appliedCoupons as $entry) {
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
            'title' => 'Quà tặng',
            'text'  => $count . ' quà',
            'value' => 0,
        ];
    }

    protected function lineReward(CheckoutContext $ctx, array &$totalData, int &$total): void
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
        foreach ($ctx->items as $item) {
            $pointsTotal += (int) ($item['points'] ?? 0);
        }
        if ($pointsTotal <= 0) {
            return;
        }

        $discountTotal = 0;
        foreach ($ctx->items as $item) {
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

    protected function lineShipping(CheckoutContext $ctx, array &$totalData, int &$total): void
    {
        $method = (string) request()->get('carrier_code');

        if (! filled($method)) {
            $this->lineFreeshipPlaceholder($ctx, $totalData);
            return;
        }

        $address = $this->extractAddress();
        $cartShipping = (array) session()->get(getCoreConfig('session.cart_shipping'), []);

        [$ok, $fee] = $this->shippingFee->calculate($method, $total, $cartShipping, $address);
        if (! $ok || $fee === null) {
            $this->lineFreeshipPlaceholder($ctx, $totalData);
            return;
        }

        $totalData[] = [
            'code'  => $method,
            'title' => trans('messages.TextFeeShipping'),
            'text'  => '+'.$this->money($fee),
            'value' => $fee,
        ];
        $total += $fee;

        if ($ctx->hasFreeshipCoupon && $fee > 0) {
            $freeshipCode = $this->findFreeshipCode($ctx);
            $totalData[] = [
                'code'  => 'coupon_freeship',
                'title' => $freeshipCode !== null
                    ? sprintf(trans('messages.TextCoupon'), $freeshipCode)
                    : trans('messages.TextCoupon', ['code' => 'FREESHIP']),
                'text'  => '-' . $this->money($fee),
                'value' => -$fee,
            ];
            $total -= $fee;
        }
    }

    protected function lineFreeshipPlaceholder(CheckoutContext $ctx, array &$totalData): void
    {
        if (! $ctx->hasFreeshipCoupon) {
            return;
        }
        $code = $this->findFreeshipCode($ctx);
        $totalData[] = [
            'code'  => 'coupon_freeship_pending',
            'title' => $code !== null
                ? sprintf(trans('messages.TextCoupon'), $code)
                : 'Voucher freeship',
            'text'  => 'Áp dụng khi chọn vận chuyển',
            'value' => 0,
        ];
    }

    protected function findFreeshipCode(CheckoutContext $ctx): ?string
    {
        $typeFreeship = (int) getCoreConfig('coupon.type.freeship');
        foreach ($ctx->appliedCoupons as $entry) {
            if ((int) ($entry['type'] ?? $entry['coupon']->type) === $typeFreeship) {
                return $entry['coupon']->code;
            }
        }
        return null;
    }

    protected function lineVouchers(array &$totalData, int &$total): void
    {
        if ($total <= 0) {
            return;
        }
        $result = $this->voucherService->resolveApplied($total);
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
                'title' => sprintf('Thẻ quà tặng %s', $voucher->code),
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
