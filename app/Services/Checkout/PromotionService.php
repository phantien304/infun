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
        protected CouponService $couponService,
        protected VoucherService $voucherService,
        protected GiftService $giftService,
    ) {
    }

    public function resolveAppliedPromotions(array $cartItems, int $subtotal, bool $hasShipping): CheckoutPromotions
    {
        $promotions = (new CheckoutPromotions())->setItems($cartItems);

        $couponCodes = (array) session()->get(getCoreConfig('session.applied_coupons'), []);
        if (! empty($couponCodes)) {
            $result = $this->couponService->applyCodes($couponCodes, $cartItems, $subtotal, contextHasShipping: $hasShipping);
            $promotions->setAppliedCoupons($result['applied'], $result['freeship']);
        }

        return $promotions->setAppliedVoucherCodes($this->voucherService->getAppliedCodes())
                          ->setAppliedGifts($this->giftService->getAppliedGifts());
    }

    public function viewData(CheckoutPromotions $promotions, int $subtotal, string $userEmail, bool $hasShipping): array
    {
        $items = $promotions->items;

        return [
            'coupons'             => $this->couponService->listForCart($items, $subtotal, contextHasShipping: $hasShipping),
            'gifts'               => $this->giftService->listForCart($items, $subtotal),
            'giftItems'           => $this->giftService->resolveGiftDisplayItems(),
            'myVouchers'          => $userEmail !== '' ? $this->voucherService->listMyVouchers($userEmail, $subtotal) : collect(),
            'appliedVoucherCodes' => $promotions->appliedVoucherCodes,
        ];
    }

    public function resolveVouchers(int $runningTotal): array
    {
        return $this->voucherService->resolveApplied($runningTotal);
    }

    public function recordForOrder(CheckoutPromotions $promotions, int $orderId, int $orderTotal): void
    {
        $this->recordCoupons($promotions, $orderId);
        $this->voucherService->recordOrderVouchers($orderId, $orderTotal);
        $this->giftService->recordOrderGifts(
            $orderId,
            $promotions->items,
            (int) array_sum(array_column($promotions->items, 'total')),
        );
    }

    public function revertForOrder(int $orderId): void
    {
        $this->couponService->revertOrderCoupons($orderId);
        $this->giftService->revertOrderGifts($orderId);
        $this->voucherService->revertOrderVouchers($orderId);
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
