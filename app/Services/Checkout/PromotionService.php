<?php

namespace App\Services\Checkout;

use App\Models\Entities\CouponHistory;
use App\Services\Cart\CouponService;
use App\Services\Cart\GiftService;
use App\Services\Cart\VoucherService;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    public function __construct(
        protected CouponService $coupons,
        protected VoucherService $vouchers,
        protected GiftService $gifts,
    ) {
    }

    public function buildContext(array $items, int $subtotal, bool $hasShipping): CheckoutPromotions
    {
        $ctx = (new CheckoutPromotions())->setItems($items);

        $couponCodes = (array) session()->get(getCoreConfig('session.applied_coupons'), []);
        if (!empty($couponCodes)) {
            $result = $this->coupons->applyCodes($couponCodes, $items, $subtotal, contextHasShipping: $hasShipping);
            $ctx->setAppliedCoupons($result['applied'], $result['freeship']);
        }

        $ctx->setVoucherCodes($this->vouchers->getAppliedCodes());
        $ctx->setGifts($this->gifts->getAppliedGifts());

        return $ctx;
    }

    public function viewData(CheckoutPromotions $ctx, int $subtotal, string $userEmail, bool $hasShipping): array
    {
        $items = $ctx->items;

        return [
            'coupons'             => $this->coupons->listForCart($items, $subtotal, contextHasShipping: $hasShipping),
            'gifts'               => $this->gifts->listForCart($items, $subtotal),
            'giftItems'           => $this->gifts->resolveGiftDisplayItems(),
            'myVouchers'          => $userEmail !== '' ? $this->vouchers->listMyVouchers($userEmail, $subtotal) : collect(),
            'appliedVoucherCodes' => $ctx->appliedVoucherCodes,
        ];
    }

    public function resolveVouchers(int $runningTotal): array
    {
        return $this->vouchers->resolveApplied($runningTotal);
    }

    public function recordForOrder(CheckoutPromotions $ctx, int $orderId, int $orderTotal): void
    {
        $this->recordCoupons($ctx, $orderId);
        $this->vouchers->recordOrderVouchers($orderId, $orderTotal);
        $this->gifts->recordOrderGifts($orderId);
    }

    public function revertForOrder(int $orderId): void
    {
        $this->coupons->revertOrderCoupons($orderId);
        $this->gifts->revertOrderGifts($orderId);
        $this->vouchers->revertOrderVouchers($orderId);
    }

    protected function recordCoupons(CheckoutPromotions $ctx, int $orderId): void
    {
        if (empty($ctx->appliedCoupons)) {
            return;
        }

        $statusUsed = (int) getCoreConfig('coupon.history_status.used');
        $userId = (int) getCurrentUserId() ?: null;

        foreach ($ctx->appliedCoupons as $entry) {
            $coupon = $entry['coupon'];
            CouponHistory::create([
                'coupon_id' => (int) $coupon->id,
                'order_id'  => $orderId,
                'user_id'   => $userId,
                'amount'    => (int) $entry['discount'],
                'status'    => $statusUsed,
            ]);
            DB::table('coupon')
                ->where('id', $coupon->id)
                ->increment('used_count');
        }
    }
}
