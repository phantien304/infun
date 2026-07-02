<?php

namespace App\Services\Checkout;

class CheckoutPromotions
{
    public array $items = [];

    public array $appliedCoupons = [];

    public bool $hasFreeshipCoupon = false;

    public array $appliedVoucherCodes = [];

    public array $appliedGifts = [];

    public ?int $orderId = null;

    public function setItems(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function setAppliedCoupons(array $applied, bool $hasFreeship): static
    {
        $this->appliedCoupons = $applied;
        $this->hasFreeshipCoupon = $hasFreeship;

        return $this;
    }

    public function setAppliedVoucherCodes(array $codes): static
    {
        $this->appliedVoucherCodes = $codes;

        return $this;
    }

    public function setAppliedGifts(array $gifts): static
    {
        $this->appliedGifts = $gifts;

        return $this;
    }

    public function setOrderId(int $id): static
    {
        $this->orderId = $id;

        return $this;
    }
}
