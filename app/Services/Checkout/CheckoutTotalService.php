<?php

namespace App\Services\Checkout;

use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use App\Services\Cart\VoucherService;

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
        protected VoucherService $voucherService,
    ) {
    }

    public function build(CheckoutContext $ctx, bool $withShipping = true): array
    {
        $totalData = [];
        $running = (int) array_sum(array_column($ctx->items, 'total'));

        $this->lineSubTotal($totalData, $running);

        // Shopee multi-coupon — single source of truth qua $ctx->appliedCoupons.
        // Voucher (gift card) áp riêng ở lineVouchers (sau shipping).
        $this->linesAppliedCoupons($ctx, $totalData, $running);
        $this->lineGifts($totalData);
        $this->lineReward($ctx, $totalData, $running);
        if ($withShipping) {
            $this->lineShipping($ctx, $totalData, $running);
        }
        // Voucher (gift card) áp SAU shipping — cover được cả phí ship.
        // Stack nhiều voucher, cap tại residual (không "trả tiền dư").
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

    /**
     * Phase 4 Shopee — render 1 line cho mỗi coupon đã áp (trừ freeship).
     * Freeship type=3 KHÔNG add line ở đây — lineShipping xử lý (zero fee).
     *
     * Mỗi entry $ctx->appliedCoupons: {coupon, discount, type}. discount đã
     * compute từ CouponService::applyCodes — chỉ việc trừ vào running total.
     */
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

    /**
     * Gift informational line — KHÔNG trừ vào total (quà miễn phí, không
     * ảnh hưởng giá). Chỉ render để user thấy "Quà tặng: N quà" trên bill
     * confirm đã chọn. Đọc trực tiếp session vì gift không có flow
     * applyCodes phức tạp như coupon.
     */
    protected function lineGifts(array &$totalData): void
    {
        $applied = (array) session()->get('checkout.applied_gifts', []);
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

    protected function lineShipping(CheckoutContext $ctx, array &$totalData, int &$total): void
    {
        $method = (string) request()->get('carrier_code');

        // Carrier chưa chọn — vẫn render placeholder freeship để user biết
        // mã đang áp + sẽ kích hoạt ở bước chọn vận chuyển. Tránh trường
        // hợp user áp 2 mã (fixed + freeship) nhưng UI chỉ hiện 1 line vì
        // freeship bị skip ở linesAppliedCoupons + lineShipping bail-out.
        if (! filled($method)) {
            $this->lineFreeshipPlaceholder($ctx, $totalData);
            return;
        }

        $address = $this->extractAddress();
        $cartShipping = (array) session()->get('cart_shipping', []);

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

        // Phase 4 — freeship coupon trừ phí ship. Add line riêng để user thấy
        // "đã tiết kiệm bao nhiêu" thay vì thấy ship = 0 không rõ vì sao.
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

    /**
     * Hiển thị 1 line confirm freeship coupon đang áp khi chưa biết phí ship
     * thật (chưa pick carrier hoặc shippingFee fail). value=0 để KHÔNG mutate
     * `total` — discount thật áp khi fee tính được sau đó.
     */
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

    /**
     * Render line cho mỗi voucher đã áp. Stack nhiều, cap tại residual total.
     * Voucher amount đã pro-rate qua `VoucherService::resolveApplied`.
     */
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
