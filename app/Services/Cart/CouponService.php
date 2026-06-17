<?php

namespace App\Services\Cart;

use App\Data\Output\CouponDTO;
use App\Models\Entities\Coupon;
use App\Models\Entities\CouponHistory;
use App\Models\Entities\ProductCategory;
use App\Models\Entities\UserCoupon;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Coupon orchestration cho cart Shopee-style.
 *
 * Trách nhiệm:
 *  - `listForCart`     : list coupon hiển thị trên modal (active + saved),
 *                        đính kèm cờ savedByUser + applicableToCart + reason.
 *  - `applyCodes`      : input array<string> codes do user pick, output
 *                        ApplyResult với discount per coupon + stacking validation.
 *  - `save`/`unsave`   : toggle bookmark vào user_coupon pivot.
 *  - `computeDiscount` : single coupon → discount VND amount.
 *  - Stacking rule: 1 discount (percent|fixed) + 1 freeship (per config).
 *
 * KHÔNG persist gì vào DB trong flow `listForCart`/`applyCodes` — chỉ tính.
 * Service order khi user submit checkout sẽ insert coupon_history rows.
 */
class CouponService
{
    public function __construct(
        protected CouponRepositoryInterface $couponRepo,
    ) {}

    /**
     * Trả mảng CouponDTO sort theo: applicable trước, expiring sớm trước,
     * sort_order ưu tiên admin.
     *
     * @param  array<int, array<string, mixed>>  $cartItems  từ CartService::getItems
     * @return Collection<int, CouponDTO>
     */
    /**
     * @param  bool  $contextHasShipping  TRUE on the checkout page where a
     *   carrier is picked and the shipping fee is included in the total.
     *   FALSE on the cart page — free-ship coupons are then flagged as
     *   "apply at checkout" so users don't pick one expecting an instant
     *   discount that the cart total can't show (the cart has no shipping
     *   line to discount yet). Default TRUE preserves callers that don't
     *   know the context (server-side fallback is the safer side).
     */
    public function listForCart(
        array $cartItems,
        int $cartSubtotal,
        ?int $userId,
        ?int $userGroupId,
        bool $contextHasShipping = true,
    ): Collection {
        $coupons = $this->couponRepo->listActiveForUser($userId, $userGroupId);
        $savedIds = $userId
            ? $this->couponRepo->listSavedByUser($userId)->pluck('id')->all()
            : [];

        // Batch số lần đã dùng/coupon trong 1 query — tránh N+1 countUsedByUser
        // khi validate từng coupon trên modal.
        $usedByUserMap = $userId
            ? $this->couponRepo->countUsedByUserForCoupons($userId, $coupons->pluck('id')->all())
            : [];

        $cartProductIds = array_unique(array_map(
            fn ($item) => (int) ($item['id'] ?? 0),
            $cartItems,
        ));
        $cartCategoryIds = $this->resolveCartCategoryIds($cartProductIds);

        return $coupons
            ->map(function (Coupon $coupon) use (
                $cartSubtotal, $cartProductIds, $cartCategoryIds, $savedIds, $userId, $contextHasShipping, $usedByUserMap
            ) {
                $reason = $this->validateForCart(
                    $coupon, $cartSubtotal, $cartProductIds, $cartCategoryIds, $userId, $contextHasShipping,
                    usedByUser: $usedByUserMap[(int) $coupon->id] ?? 0,
                );
                return CouponDTO::fromModel(
                    $coupon,
                    savedByUser: in_array((int) $coupon->id, $savedIds, true),
                    applicableToCart: $reason === null,
                    notApplicableReason: $reason,
                );
            })
            ->sortBy([
                ['applicableToCart', 'desc'],   // applicable first
                ['savedByUser', 'desc'],         // saved on top within the same bucket
            ])
            ->values();
    }

    /**
     * Validate 1 coupon với cart context. Trả NULL nếu OK, hoặc reason string
     * nếu không áp được.
     */
    public function validateForCart(
        Coupon $coupon,
        int $cartSubtotal,
        array $cartProductIds,
        array $cartCategoryIds,
        ?int $userId,
        bool $contextHasShipping = true,
        ?int $usedByUser = null,
    ): ?string {
        // Free-ship coupons can only be applied when the running total
        // already includes a shipping fee. On the cart page (no carrier,
        // no fee) we surface a friendly reason instead of letting the user
        // pick a coupon that visually does nothing.
        if (
            ! $contextHasShipping
            && (int) $coupon->type === (int) getCoreConfig('coupon.type.freeship')
        ) {
            return 'Áp dụng tại bước Thanh toán';
        }

        if ($coupon->min_subtotal !== null && $cartSubtotal < (float) $coupon->min_subtotal) {
            $missingAmount = (float) $coupon->min_subtotal - $cartSubtotal;
            return 'Cần mua thêm ' . number_format($missingAmount) . (string) getConfigDb('config_currency');
        }

        if ($coupon->logged && $userId === null) {
            return 'Cần đăng nhập';
        }

        if ($coupon->uses_total !== null && (int) $coupon->used_count >= (int) $coupon->uses_total) {
            return 'Đã hết lượt sử dụng';
        }

        if ($userId !== null && $coupon->uses_customer !== null) {
            // $usedByUser được listForCart truyền sẵn (batch). Caller khác
            // (applyCodes — ít code) không truyền → query lẻ, chấp nhận được.
            $used = $usedByUser ?? $this->couponRepo->countUsedByUser($userId, (int) $coupon->id);
            if ($used >= (int) $coupon->uses_customer) {
                return 'Bạn đã dùng hết lượt';
            }
        }

        $scope = (int) $coupon->apply_scope;
        if ($scope === (int) getCoreConfig('coupon.apply_scope.products')) {
            $allowedProductIds = $coupon->couponProducts->pluck('product_id')->all();
            if (empty(array_intersect($cartProductIds, $allowedProductIds))) {
                return 'Không áp dụng cho SP trong giỏ';
            }
        }

        if ($scope === (int) getCoreConfig('coupon.apply_scope.categories')) {
            $allowedCategoryIds = $coupon->couponCategories->pluck('category_id')->all();
            if (empty(array_intersect($cartCategoryIds, $allowedCategoryIds))) {
                return 'Không áp dụng cho ngành hàng trong giỏ';
            }
        }

        return null;
    }

    /**
     * Compute discount amount (VND) cho 1 coupon đã pass validate.
     *
     * - percent  : min(subtotal × discount%, discount_max)
     * - fixed    : min(discount, subtotal)
     * - freeship : 0 (caller áp riêng vào shipping_fee → 0)
     *
     * Param `applicableSubtotal` cần caller tự tính theo apply_scope (nếu chỉ
     * 1 số SP → tính tổng các SP đó, không phải cart subtotal full).
     */
    public function computeDiscount(Coupon $coupon, int $applicableSubtotal): int
    {
        $type = (int) $coupon->type;

        if ($type === (int) getCoreConfig('coupon.type.percent')) {
            $rawDiscount = (int) floor($applicableSubtotal * ((float) $coupon->discount / 100));
            if ($coupon->discount_max !== null) {
                $rawDiscount = min($rawDiscount, (int) $coupon->discount_max);
            }
            return min($rawDiscount, $applicableSubtotal);
        }

        if ($type === (int) getCoreConfig('coupon.type.fixed')) {
            return min((int) $coupon->discount, $applicableSubtotal);
        }

        if ($type === (int) getCoreConfig('coupon.type.freeship')) {
            return 0; // caller xử lý ở shipping fee branch
        }

        return 0;
    }

    /**
     * Apply nhiều coupon codes cùng lúc (Shopee stack rule).
     *
     * Stacking: 1 discount (percent|fixed) + 1 freeship. Cùng loại discount
     * chỉ giữ 1 — ưu tiên cái giảm nhiều VND hơn.
     *
     * @param  array<int, string>  $codes
     * @return array{
     *     applied: array<int, array{coupon: Coupon, discount: int, type: int}>,
     *     errors:  array<int, string>,
     *     total_discount: int,
     *     freeship: bool,
     * }
     */
    public function applyCodes(
        array $codes,
        array $cartItems,
        int $cartSubtotal,
        ?int $userId,
        ?int $userGroupId,
        bool $contextHasShipping = true,
    ): array {
        $cartProductIds = array_unique(array_map(
            fn ($item) => (int) ($item['id'] ?? 0),
            $cartItems,
        ));
        $cartCategoryIds = $this->resolveCartCategoryIds($cartProductIds);

        $applied = [];
        $errors = [];
        $bestDiscount = null;
        $bestFreeship = null;

        $typeFreeship = (int) getCoreConfig('coupon.type.freeship');
        $allowStack = (bool) getCoreConfig('coupon.stacking.allow_freeship_with_discount');

        foreach (array_unique(array_map('strval', $codes)) as $code) {
            $coupon = $this->couponRepo->findByCode($code);
            if (! $coupon) {
                $errors[] = "Mã '{$code}' không tồn tại";
                continue;
            }

            // Re-check active + user_group at runtime (the cached list can be stale).
            $activeQuery = Coupon::query()
                ->where('id', $coupon->id)
                ->active()
                ->forUserGroupOrPublic($userGroupId);
            if (! $activeQuery->exists()) {
                $errors[] = "Mã '{$code}' không còn hiệu lực";
                continue;
            }

            $reason = $this->validateForCart(
                $coupon, $cartSubtotal, $cartProductIds, $cartCategoryIds, $userId, $contextHasShipping,
            );
            if ($reason !== null) {
                $errors[] = "Mã '{$code}': {$reason}";
                continue;
            }

            $applicableSubtotal = $this->resolveApplicableSubtotal($coupon, $cartItems, $cartSubtotal);
            $discount = $this->computeDiscount($coupon, $applicableSubtotal);
            $type = (int) $coupon->type;

            if ($type === $typeFreeship) {
                if ($bestFreeship === null) {
                    $bestFreeship = ['coupon' => $coupon, 'discount' => $discount, 'type' => $type];
                }
                continue;
            }

            // Stacking: giữ discount lớn hơn.
            if ($bestDiscount === null || $discount > $bestDiscount['discount']) {
                $bestDiscount = ['coupon' => $coupon, 'discount' => $discount, 'type' => $type];
            }
        }

        if ($bestDiscount) {
            $applied[] = $bestDiscount;
        }
        if ($bestFreeship && ($allowStack || $bestDiscount === null)) {
            $applied[] = $bestFreeship;
        } elseif ($bestFreeship && ! $allowStack && $bestDiscount !== null) {
            $errors[] = 'Không thể stack mã freeship với mã giảm giá';
        }

        return [
            'applied'        => $applied,
            'errors'         => $errors,
            'total_discount' => array_sum(array_column($applied, 'discount')),
            'freeship'       => $bestFreeship !== null && in_array($bestFreeship, $applied, true),
        ];
    }

    public function save(int $userId, int $couponId): bool
    {
        $exists = Coupon::query()->where('id', $couponId)->exists();
        if (! $exists) {
            return false;
        }

        UserCoupon::query()->updateOrInsert(
            ['user_id' => $userId, 'coupon_id' => $couponId],
            ['saved_at' => now()],
        );
        return true;
    }

    public function unsave(int $userId, int $couponId): bool
    {
        return UserCoupon::query()
            ->where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->delete() > 0;
    }

    /**
     * Insert coupon_history status=applied khi user check coupon trong cart.
     * Trả id history mới — caller persist vào order khi submit checkout.
     */
    public function recordApplied(int $couponId, ?int $userId, int $amount): int
    {
        return (int) CouponHistory::query()->insertGetId([
            'coupon_id'  => $couponId,
            'order_id'   => null,
            'user_id'    => $userId,
            'amount'     => $amount,
            'status'     => (int) getCoreConfig('coupon.history_status.applied'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Revert quota khi order huỷ:
     *  - Flip mọi coupon_history.status (used → cancelled) gắn với order_id.
     *  - Decrement coupon.used_count tương ứng (chỉ với row vừa flip — tránh
     *    trừ 2 lần khi cancel idempotent).
     *
     * Gọi từ AccountService::cancelOrder TRONG cùng DB transaction. Idempotent:
     * gọi lại không sai vì WHERE status = used filter.
     */
    public function revertOrderCoupons(int $orderId): void
    {
        $statusUsed = (int) getCoreConfig('coupon.history_status.used');
        $statusCancelled = (int) getCoreConfig('coupon.history_status.cancelled');

        $toRevert = CouponHistory::query()
            ->where('order_id', $orderId)
            ->where('status', $statusUsed)
            ->get(['id', 'coupon_id']);

        if ($toRevert->isEmpty()) {
            return;
        }

        CouponHistory::query()
            ->whereIn('id', $toRevert->pluck('id')->all())
            ->update(['status' => $statusCancelled, 'updated_at' => now()]);

        // Decrement used_count per coupon. Batch by coupon_id để 1 UPDATE/coupon
        // dù có nhiều history row cùng coupon (case admin cho phép user dùng
        // 2x — current schema chỉ 1 row/coupon/order, an toàn).
        foreach ($toRevert->groupBy('coupon_id') as $couponId => $rows) {
            DB::table('coupon')
                ->where('id', (int) $couponId)
                ->where('used_count', '>=', $rows->count())
                ->decrement('used_count', $rows->count());
        }
    }

    /**
     * Helpers
     */

    private function resolveApplicableSubtotal(Coupon $coupon, array $cartItems, int $cartSubtotal): int
    {
        $scope = (int) $coupon->apply_scope;
        if ($scope === (int) getCoreConfig('coupon.apply_scope.all')) {
            return $cartSubtotal;
        }

        if ($scope === (int) getCoreConfig('coupon.apply_scope.products')) {
            $allowed = $coupon->couponProducts->pluck('product_id')->all();
            return (int) array_sum(array_map(
                fn ($item) => in_array((int) ($item['id'] ?? 0), $allowed, true)
                    ? (int) ($item['total'] ?? 0) : 0,
                $cartItems,
            ));
        }

        if ($scope === (int) getCoreConfig('coupon.apply_scope.categories')) {
            $allowed = $coupon->couponCategories->pluck('category_id')->all();
            $productInCat = $this->productIdsInCategories(
                array_map(fn ($item) => (int) ($item['id'] ?? 0), $cartItems),
                $allowed,
            );
            return (int) array_sum(array_map(
                fn ($item) => in_array((int) ($item['id'] ?? 0), $productInCat, true)
                    ? (int) ($item['total'] ?? 0) : 0,
                $cartItems,
            ));
        }

        return $cartSubtotal;
    }

    private function resolveCartCategoryIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        return ProductCategory::query()
            ->whereIn('product_id', $productIds)
            ->pluck('category_id')
            ->unique()
            ->values()
            ->all();
    }

    private function productIdsInCategories(array $productIds, array $categoryIds): array
    {
        if (empty($productIds) || empty($categoryIds)) {
            return [];
        }
        return ProductCategory::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('category_id', $categoryIds)
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();
    }
}
