<?php

namespace App\Services\Cart;

use App\Data\Output\CouponDTO;
use App\Models\Entities\Coupon;
use App\Repositories\Interfaces\CouponHistoryRepositoryInterface;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;
use App\Repositories\Interfaces\UserCouponRepositoryInterface;
use Illuminate\Support\Collection;

class CouponService
{
    public function __construct(
        protected CouponRepositoryInterface $couponRepo,
        protected CouponHistoryRepositoryInterface $couponHistoryRepo,
        protected UserCouponRepositoryInterface $userCouponRepo,
        protected ProductCategoryRepositoryInterface $productCategoryRepo,
    ) {
    }

    private array $cartContextCache = [];

    public function listForCart(array $cartItems, int $cartSubtotal, bool $contextHasShipping = true): Collection
    {
        $userId = (int) getCurrentUserId() ?: null;
        $userGroupId = getUserGroupId() ?: null;

        $coupons = $this->couponRepo->listActiveForUser($userGroupId);
        $savedIds = $userId
            ? $this->couponRepo->listSavedByUser($userId)->pluck('id')->all()
            : [];

        $usedByUserMap = $userId
            ? $this->couponHistoryRepo->countUsedByUserForCoupons($userId, $coupons->pluck('id')->all())
            : [];

        $cartContext = $this->buildCartContext($cartItems, $cartSubtotal, $userId, $contextHasShipping);

        return $coupons
            ->map(function (Coupon $coupon) use ($cartContext, $savedIds, $usedByUserMap) {
                $reason = $this->validateForCart(
                    $coupon,
                    $cartContext,
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
                ['applicableToCart', 'desc'],
                ['savedByUser', 'desc'],
            ])
            ->values();
    }

    public function validateForCart(Coupon $coupon, CartCouponContext $cartContext, ?int $usedByUser = null): ?string
    {
        if (! $cartContext->hasShipping && (int) $coupon->type === (int) getCoreConfig('coupon.type.freeship')) {
            return trans('messages.checkout.coupon.apply_at_checkout');
        }

        if ($coupon->min_subtotal !== null && $cartContext->subtotal < (float) $coupon->min_subtotal) {
            $missingAmount = (float) $coupon->min_subtotal - $cartContext->subtotal;
            return sprintf(
                trans('messages.checkout.coupon.need_more'),
                number_format($missingAmount) . (string) getConfigDb('config_currency'),
            );
        }

        if ($coupon->logged && $cartContext->userId === null) {
            return trans('messages.checkout.coupon.need_login');
        }

        if ($coupon->uses_total !== null && (int) $coupon->used_count >= (int) $coupon->uses_total) {
            return trans('messages.checkout.coupon.used_up_total');
        }

        if ($cartContext->userId !== null && $coupon->uses_customer !== null) {
            $used = $usedByUser ?? $this->couponHistoryRepo->countUsedByUser($cartContext->userId, (int) $coupon->id);
            if ($used >= (int) $coupon->uses_customer) {
                return trans('messages.checkout.coupon.used_up_user');
            }
        }

        $scope = (int) $coupon->apply_scope;
        if ($scope === (int) getCoreConfig('coupon.apply_scope.products')) {
            $allowedProductIds = $coupon->couponProducts->pluck('product_id')->all();
            if (empty(array_intersect($cartContext->productIds, $allowedProductIds))) {
                return trans('messages.checkout.coupon.scope_products');
            }
        }

        if ($scope === (int) getCoreConfig('coupon.apply_scope.categories')) {
            $allowedCategoryIds = $coupon->couponCategories->pluck('category_id')->all();
            if (empty(array_intersect($cartContext->categoryIds, $allowedCategoryIds))) {
                return trans('messages.checkout.coupon.scope_categories');
            }
        }

        return null;
    }

    private function buildCartContext(array $cartItems, int $cartSubtotal, ?int $userId, bool $hasShipping): CartCouponContext
    {
        $productIds = array_unique(array_map(
            fn ($item) => (int) ($item['id'] ?? 0),
            $cartItems,
        ));

        $cacheKey = md5($cartSubtotal . '|' . ($userId ?? 0) . '|' . ($hasShipping ? '1' : '0') . '|' . implode(',', $productIds));
        if (isset($this->cartContextCache[$cacheKey])) {
            return $this->cartContextCache[$cacheKey];
        }

        return $this->cartContextCache[$cacheKey] = new CartCouponContext(
            subtotal:    $cartSubtotal,
            productIds:  $productIds,
            categoryIds: $this->resolveCartCategoryIds($productIds),
            userId:      $userId,
            hasShipping: $hasShipping,
        );
    }

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
            return 0;
        }

        return 0;
    }

    public function applyCodes(array $codes, array $cartItems, int $cartSubtotal, bool $contextHasShipping = true): array
    {
        $userId = (int) getCurrentUserId() ?: null;
        $userGroupId = getUserGroupId() ?: null;

        $cartContext = $this->buildCartContext($cartItems, $cartSubtotal, $userId, $contextHasShipping);

        $applied = [];
        $errors = [];
        $bestDiscount = null;
        $bestFreeship = null;

        $typeFreeship = (int) getCoreConfig('coupon.type.freeship');
        $allowStack = (bool) getCoreConfig('coupon.stacking.allow_freeship_with_discount');

        foreach (array_unique(array_map('strval', $codes)) as $code) {
            $coupon = $this->couponRepo->findByCode($code);
            if (! $coupon) {
                $errors[] = sprintf(trans('messages.checkout.coupon.not_found'), $code);
                continue;
            }

            if (! $this->couponRepo->isActiveForUserGroup((int) $coupon->id, $userGroupId)) {
                $errors[] = sprintf(trans('messages.checkout.coupon.inactive'), $code);
                continue;
            }

            $reason = $this->validateForCart($coupon, $cartContext);
            if ($reason !== null) {
                $errors[] = sprintf(trans('messages.checkout.coupon.error_prefix'), $code, $reason);
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
            $errors[] = trans('messages.checkout.coupon.no_stack');
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
        if (! $this->couponRepo->existsById($couponId)) {
            return false;
        }

        $this->userCouponRepo->saveForUser($userId, $couponId);
        return true;
    }

    public function unsave(int $userId, int $couponId): bool
    {
        return $this->userCouponRepo->unsaveForUser($userId, $couponId);
    }

    public function recordApplied(int $couponId, ?int $userId, int $amount): int
    {
        return $this->couponHistoryRepo->recordApplied(
            $couponId,
            $userId,
            $amount,
            (int) getCoreConfig('coupon.history_status.applied'),
        );
    }

    public function recordUsedForOrder(int $couponId, int $orderId, ?int $userId, int $amount): void
    {
        $this->couponHistoryRepo->recordUsed(
            $couponId,
            $orderId,
            $userId,
            $amount,
            (int) getCoreConfig('coupon.history_status.used'),
        );
        $this->couponRepo->incrementUsedCount($couponId);
    }

    public function revertOrderCoupons(int $orderId): void
    {
        $statusUsed = (int) getCoreConfig('coupon.history_status.used');
        $statusCancelled = (int) getCoreConfig('coupon.history_status.cancelled');

        $toRevert = $this->couponHistoryRepo->forOrderByStatus($orderId, $statusUsed);
        if ($toRevert->isEmpty()) {
            return;
        }

        $this->couponHistoryRepo->markStatus($toRevert->pluck('id')->all(), $statusCancelled);

        foreach ($toRevert->groupBy('coupon_id') as $couponId => $rows) {
            $this->couponRepo->decrementUsedCount((int) $couponId, $rows->count());
        }
    }

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
        return $this->productCategoryRepo->categoryIdsForProducts($productIds);
    }

    private function productIdsInCategories(array $productIds, array $categoryIds): array
    {
        return $this->productCategoryRepo->productIdsInCategories($productIds, $categoryIds);
    }
}
