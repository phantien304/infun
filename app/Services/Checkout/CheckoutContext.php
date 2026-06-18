<?php

namespace App\Services\Checkout;

class CheckoutContext
{
    public array $items = [];

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
