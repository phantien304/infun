<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Services\Cart\CouponService;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutPromotions;
use App\Services\Checkout\CheckoutTotalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutCouponController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CouponService $couponService,
        protected CouponRepositoryInterface $couponRepo,
        protected CheckoutTotalService $totalService,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $items     = $this->cartService->getItems();
        $subtotal  = $this->cartService->getSubtotal();
        $hasShipping = $this->contextHasShipping($request);

        $coupons = $this->couponService->listForCart(
            $items,
            (int) $subtotal,
            contextHasShipping: $hasShipping,
        );

        return respondSuccess([
            'coupons'       => $coupons->all(),
            'applied_codes' => $this->getAppliedCodes(),
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $codes = (array) ($request->input('codes') ?? [$request->input('code')]);
        $codes = array_values(array_filter(array_map('strval', $codes), fn ($c) => trim($c) !== ''));

        if (empty($codes)) {
            return respondUnprocessable(trans('messages.checkout.coupon_choose_required'));
        }
        $items     = $this->cartService->getItems();
        $subtotal  = $this->cartService->getSubtotal();
        $hasShipping = $this->contextHasShipping($request);

        $result = $this->couponService->applyCodes($codes, $items, (int) $subtotal, contextHasShipping: $hasShipping);
        if (empty($result['applied'])) {
            $msg = $result['errors'][0] ?? trans('messages.checkout.coupon_none_applied');
            return respondUnprocessable($msg);
        }

        $appliedCodes = array_map(fn ($a) => $a['coupon']->code, $result['applied']);
        session()->put(getCoreConfig('session.applied_coupons'), $appliedCodes);

        return respondSuccess($this->renderState($request, $hasShipping, [
            'total_discount' => $result['total_discount'],
            'errors'         => $result['errors'],
        ], $result));
    }

    public function save(Request $request): JsonResponse
    {
        $userId = (int) getCurrentUserId();
        if ($userId <= 0) {
            return respondError(trans('messages.checkout.coupon_login_save'), 401);
        }

        $couponId = (int) $request->input('coupon_id', 0);
        if ($couponId <= 0) {
            return respondUnprocessable(trans('messages.checkout.coupon_invalid'));
        }

        $ok = $this->couponService->save($userId, $couponId);
        if (! $ok) {
            return respondUnprocessable(trans('messages.checkout.coupon_not_exist'));
        }

        return respondMessage(trans('messages.checkout.coupon_saved'));
    }

    public function unsave(Request $request): JsonResponse
    {
        $userId = (int) getCurrentUserId();
        if ($userId <= 0) {
            return respondError(trans('messages.checkout.login_required'), 401);
        }

        $couponId = (int) $request->input('coupon_id', 0);
        $this->couponService->unsave($userId, $couponId);

        return respondMessage(trans('messages.checkout.coupon_unsaved'));
    }

    public function remove(Request $request): JsonResponse
    {
        session()->forget(getCoreConfig('session.applied_coupons'));

        return respondSuccess($this->renderState($request, $this->contextHasShipping($request)));
    }

    private function getAppliedCodes(): array
    {
        $codes = session()->get(getCoreConfig('session.applied_coupons'), []);
        if (! is_array($codes)) {
            return [];
        }
        return array_values(array_filter($codes, 'is_string'));
    }

    private function contextHasShipping(Request $request): bool
    {
        return $request->input('context') === 'checkout';
    }

    private function renderState(Request $request, bool $hasShipping, array $extra = [], ?array $applyResult = null): array
    {
        $items     = $this->cartService->getItems();
        $subtotal  = (int) $this->cartService->getSubtotal();

        $ctx = new CheckoutPromotions();
        $ctx->setItems($items);
        $appliedCodes = (array) session()->get(getCoreConfig('session.applied_coupons'), []);
        if ($applyResult === null && ! empty($appliedCodes)) {
            $applyResult = $this->couponService->applyCodes($appliedCodes, $items, $subtotal, contextHasShipping: $hasShipping);
        }
        if (! empty($applyResult['applied'] ?? [])) {
            $ctx->setAppliedCoupons(
                $applyResult['applied'],
                $applyResult['freeship'],
            );
            $appliedCodes = array_map(fn ($a) => $a['coupon']->code, $applyResult['applied']);
            session()->put(getCoreConfig('session.applied_coupons'), $appliedCodes);
        }

        [$totalData, $total] = $this->totalService->build($ctx, withShipping: $hasShipping);

        $coupons = $this->couponService->listForCart($items, $subtotal, contextHasShipping: $hasShipping);

        $totalDataHtml = view('web::checkout._total_data_rows', [
            'totalData' => $totalData,
        ])->render();

        $promoRowHtml = view('web::checkout._coupon_promo_row', [
            'coupons'            => $coupons,
            'appliedCouponCodes' => $appliedCodes,
        ])->render();

        return array_merge([
            'total_data_html' => $totalDataHtml,
            'promo_row_html'  => $promoRowHtml,
            'applied_codes'   => $appliedCodes,
            'total'           => $total,
        ], $extra);
    }
}
