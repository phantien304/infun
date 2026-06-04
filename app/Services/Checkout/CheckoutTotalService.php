<?php

namespace App\Services\Checkout;

use App\Repositories\Interfaces\UserRewardRepositoryInterface;

/**
 * Tính toàn bộ các dòng trong "Hóa đơn của bạn": sub_total, coupon, voucher,
 * reward, shipping, total. Port logic từ trait CheckoutTotal cũ.
 *
 * Output: mảng `totalData` mỗi entry `{code, title, text, value}`. Blade
 * (cart.blade.php, index.blade.php) đọc đúng shape này — giữ contract.
 *
 * `total` được mutate qua reference theo từng step (giảm/cộng dồn) — match
 * semantics cũ. Order các step bắt buộc:
 *   sub_total → coupon → voucher → reward → shipping → total
 * vì coupon được tính trên sub_total, voucher trên (sub-coupon), reward
 * theo points (đại lượng riêng), shipping trên (sub-coupon-voucher-reward),
 * total là tổng cuối.
 */
class CheckoutTotalService
{
    public function __construct(
        protected ShippingFeeService $shippingFee,
        protected UserRewardRepositoryInterface $rewardRepo,
    ) {
    }

    public function build(CheckoutContext $ctx, bool $withShipping = true): array
    {
        $totalData = [];
        $running = (int) array_sum(array_column($ctx->items, 'total'));

        $this->lineSubTotal($totalData, $running);
        $this->lineCoupon($ctx, $totalData, $running);
        $this->lineVoucher($ctx, $totalData, $running);
        $this->lineReward($ctx, $totalData, $running);
        if ($withShipping) {
            $this->lineShipping($totalData, $running);
        }
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

    protected function lineCoupon(CheckoutContext $ctx, array &$totalData, int &$total): void
    {
        if (! session()->has('coupon') || empty($ctx->coupon)) {
            return;
        }

        $coupon = $ctx->coupon;
        $items = $ctx->items;

        if (empty($coupon['product'])) {
            $subTotal = (int) array_sum(array_column($items, 'total'));
        } else {
            $subTotal = 0;
            foreach ($items as $item) {
                if (in_array($item['id'], $coupon['product'], true)) {
                    $subTotal += (int) $item['total'];
                }
            }
        }

        if ($coupon['type'] === 'F') {
            $coupon['discount'] = min((int) $coupon['discount'], $subTotal);
        } elseif ($coupon['type'] === 'P' && (int) $coupon['discount'] > 100) {
            $coupon['discount'] = 100;
        }

        $discountTotal = 0;
        foreach ($items as $item) {
            $apply = empty($coupon['product']) || in_array($item['id'], $coupon['product'], true);
            if (! $apply || $subTotal <= 0) {
                continue;
            }
            if ($coupon['type'] === 'F') {
                $discountTotal += (int) ($coupon['discount'] * ($item['total'] / $subTotal));
            } elseif ($coupon['type'] === 'P') {
                $discountTotal += (int) ($item['total'] / 100 * $coupon['discount']);
            }
        }

        $totalData[] = [
            'code'  => 'coupon',
            'title' => sprintf(trans('messages.TextCoupon'), session()->get('coupon')),
            'text'  => '-'.$this->money($discountTotal),
            'value' => -$discountTotal,
        ];
        $total -= $discountTotal;
    }

    protected function lineVoucher(CheckoutContext $ctx, array &$totalData, int &$total): void
    {
        if (! session()->has('voucher') || empty($ctx->voucher)) {
            return;
        }

        $amount = (int) $ctx->voucher['amount'];
        $amount = $amount > $total ? $total : $amount;

        $totalData[] = [
            'code'  => 'voucher',
            'title' => sprintf(trans('messages.TextVoucher'), session()->get('voucher')),
            'text'  => '-'.$this->money($amount),
            'value' => -$amount,
        ];
        $total -= $amount;
    }

    protected function lineReward(CheckoutContext $ctx, array &$totalData, int &$total): void
    {
        if (getConfigDb('config_reward_point_enabled') == setting('reward_point.disable')) {
            return;
        }
        if (! session()->has('reward') || ! auth()->check()) {
            return;
        }

        $available = $this->rewardRepo->getTotalPoints((int) auth()->id());
        $reward = (int) session()->get('reward');
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

    protected function lineShipping(array &$totalData, int &$total): void
    {
        $method = (string) request()->get('carrier_code');
        if (! filled($method)) {
            return;
        }

        $address = $this->extractAddress();
        $cartShipping = (array) session()->get('cart_shipping', []);

        [$ok, $fee] = $this->shippingFee->calculate($method, $total, $cartShipping, $address);
        if (! $ok || $fee === null) {
            return;
        }

        $totalData[] = [
            'code'  => $method,
            'title' => trans('messages.TextFeeShipping'),
            'text'  => '+'.$this->money($fee),
            'value' => $fee,
        ];
        $total += $fee;
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

    protected function money(int $v): string
    {
        return number_format($v, 0, '', ',').'đ';
    }
}
