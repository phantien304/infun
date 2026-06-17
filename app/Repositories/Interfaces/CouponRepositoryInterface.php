<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Coupon;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface CouponRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * [Legacy — KHÔNG dùng cho flow Shopee mới] Resolve coupon code thành mảng
     * thông tin dùng cho tính giảm giá. Trả [] nếu code không hợp lệ.
     *
     * Giữ cho backward-compat với trait CheckoutMarketing legacy. Code mới gọi
     * CouponService::applyCodes thay vào đây.
     *
     * @param  array<int, array<string, mixed>>  $cartItems
     * @param  int                               $cartSubtotal
     */
    public function resolveCoupon(?string $code, array $cartItems, int $cartSubtotal): array;

    /**
     * Tất cả coupon active hiện tại (is_active + window + quota chưa hết)
     * + filter user_group_id NULL hoặc khớp user. KHÔNG check apply_scope
     * (cần cart context — đặt ở CouponService::validateForCart).
     *
     * @return Collection<int, Coupon>
     */
    public function listActiveForUser(?int $userId, ?int $userGroupId): Collection;

    /**
     * Voucher user đã lưu vào "Mã của tôi". Trả cả expired/inactive để hiển
     * thị state "không còn hiệu lực" thay vì biến mất.
     *
     * @return Collection<int, Coupon>
     */
    public function listSavedByUser(int $userId): Collection;

    public function findByCode(string $code): ?Coupon;

    /**
     * Đếm số lần user đã dùng coupon (status applied + used) → check
     * uses_per_customer quota.
     */
    public function countUsedByUser(int $userId, int $couponId): int;

    /**
     * Batch đếm số lần user đã dùng (applied + used) trên nhiều coupon — tránh
     * N+1 ở CouponService::listForCart.
     *
     * @param  array<int, int>  $couponIds
     * @return array<int, int>  [coupon_id => count]
     */
    public function countUsedByUserForCoupons(int $userId, array $couponIds): array;

    public function flushCache(): void;
}
