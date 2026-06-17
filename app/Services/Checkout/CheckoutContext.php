<?php

namespace App\Services\Checkout;

/**
 * Container chia sẻ state giữa các service trong 1 request checkout:
 *  - cart items đã enrich (từ CartService::getItems)
 *  - appliedCoupons đã resolve (từ CouponService::applyCodes)
 *  - order id sau khi tạo (để sub-step ghi coupon_history/voucher_history/...)
 *
 * Service nhận context qua tham số method thay vì shared property, để dễ test.
 * Đây chỉ là dumb DTO (mutable) — không có business logic.
 */
class CheckoutContext
{
    public array $items = [];

    /**
     * Shopee-style multi-coupon — array kết quả CouponService::applyCodes,
     * mỗi entry `{coupon: Coupon, discount: int, type: int}`.
     *
     * @var array<int, array{coupon: \App\Models\Entities\Coupon, discount: int, type: int}>
     */
    public array $appliedCoupons = [];

    public bool $hasFreeshipCoupon = false;

    public int $totalCouponDiscount = 0;

    public ?int $orderId = null;

    public int $userGroupId = 0;

    public function setItems(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function setAppliedCoupons(array $applied, bool $hasFreeship, int $totalDiscount): static
    {
        $this->appliedCoupons = $applied;
        $this->hasFreeshipCoupon = $hasFreeship;
        $this->totalCouponDiscount = $totalDiscount;

        return $this;
    }

    public function setOrderId(int $id): static
    {
        $this->orderId = $id;

        return $this;
    }
}
