<?php

namespace App\Services\Checkout;

/**
 * Container chia sẻ state giữa các service trong 1 request checkout:
 *  - cart items đã enrich (từ CartService::getItems)
 *  - coupon đã resolve (từ CouponRepository)
 *  - voucher đã resolve (từ VoucherRepository)
 *  - order id sau khi tạo (để sub-step ghi history/coupon_history/...)
 *
 * Service nhận context qua tham số method thay vì shared property, để dễ test.
 * Đây chỉ là dumb DTO (mutable) — không có business logic.
 */
class CheckoutContext
{
    public array $items = [];

    public array $coupon = [];

    public array $voucher = [];

    public ?int $orderId = null;

    public int $userGroupId = 0;

    public function setItems(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function setCoupon(array $coupon): static
    {
        $this->coupon = $coupon;

        return $this;
    }

    public function setVoucher(array $voucher): static
    {
        $this->voucher = $voucher;

        return $this;
    }

    public function setOrderId(int $id): static
    {
        $this->orderId = $id;

        return $this;
    }
}
