<?php

namespace App\Services\Checkout;

use App\Services\Cart\CouponService;
use App\Services\Cart\GiftService;
use App\Services\Cart\VoucherService;

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
            $promotions->setAppliedCoupons($result['couponApplied'], $result['freeship']);
        }

        $this->giftService->pruneInvalid($cartItems, $subtotal);

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
        $promotions->droppedGifts = $this->giftService->recordOrderGifts(
            $orderId,
            $promotions->items,
            (int) array_sum(array_column($promotions->items, 'total')),
        );
    }

    public function revertForOrder(int $orderId): void
    {
        $this->couponService->revertOrderCoupons($orderId);
        $this->voucherService->revertOrderVouchers($orderId);
        $this->giftService->revertOrderGifts($orderId);
    }

    protected function recordCoupons(CheckoutPromotions $promotions, int $orderId): void
    {
        if (empty($promotions->appliedCoupons)) {
            return;
        }

        $userId = (int) getCurrentUserId() ?: null;

        foreach ($promotions->appliedCoupons as $entry) {
            $coupon = $entry['coupon'];
            $this->couponService->recordUsedForOrder(
                (int) $coupon->id,
                $orderId,
                $userId,
                (int) $entry['discount'],
                $coupon->uses_customer !== null ? (int) $coupon->uses_customer : null,
            );
        }
    }
}
