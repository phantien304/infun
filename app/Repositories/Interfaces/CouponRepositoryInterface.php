<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface CouponRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Resolve coupon code thành mảng thông tin dùng cho tính giảm giá. Trả []
     * nếu code không hợp lệ (hết hạn / vượt limit / không match product hoặc
     * category nào trong cart).
     *
     * @param  array<int, array<string, mixed>>  $cartItems  list cart items đã build
     * @param  int                               $cartSubtotal  subtotal hiện tại để check min order
     */
    public function resolveCoupon(?string $code, array $cartItems, int $cartSubtotal): array;
}
