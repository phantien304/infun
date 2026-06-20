<?php

namespace App\Services\Cart;

final class CartCouponContext
{
    public function __construct(
        public readonly int $subtotal,
        public readonly array $productIds,
        public readonly array $categoryIds,
        public readonly ?int $userId,
        public readonly bool $hasShipping = true,
    ) {
    }
}
