<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\CouponDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Services\Cart\CouponService;
use App\Services\CartService;
use App\Services\Checkout\CheckoutPromotions;
use App\Services\Checkout\CheckoutTotalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutCouponController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CouponService $couponService,
        protected CouponRepositoryInterface $couponRepo,
        protected CheckoutTotalService $totalService,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $items     = $this->cart->getItems();
        $subtotal  = $this->cart->getSubtotal();
        $hasShipping = $this->contextHasShipping($request);

        $coupons = $this->couponService->listForCart(
            $items,
            (int) $subtotal,
            contextHasShipping: $hasShipping,
        );

        return successData('SearchSuccess', [
            'coupons'       => $coupons->all(),
            'applied_codes' => $this->getAppliedCodes(),
        ], $coupons->count());
    }

    public function apply(Request $request): JsonResponse
    {
        $codes = (array) ($request->input('codes') ?? [$request->input('code')]);
        $codes = array_values(array_filter(array_map('strval', $codes), fn ($c) => trim($c) !== ''));

        if (empty($codes)) {
            return errValidator('Vui lòng chọn voucher', 200);
        }
        $items     = $this->cart->getItems();
        $subtotal  = $this->cart->getSubtotal();
        $hasShipping = $this->contextHasShipping($request);

        $result = $this->couponService->applyCodes($codes, $items, (int) $subtotal, contextHasShipping: $hasShipping);
        if (empty($result['applied'])) {
            $msg = $result['errors'][0] ?? 'Không có voucher nào áp dụng được';
            return errValidator($msg, 200);
        }

        $appliedCodes = array_map(fn ($a) => $a['coupon']->code, $result['applied']);
        session()->put(getCoreConfig('session.applied_coupons'), $appliedCodes);

        return successData('Success', $this->renderState($request, $hasShipping, [
            'total_discount' => $result['total_discount'],
            'errors'         => $result['errors'],
        ], $result));
    }

    public function save(Request $request): JsonResponse
    {
        $userId = (int) getCurrentUserId();
        if ($userId <= 0) {
            return errValidator('Vui lòng đăng nhập để lưu voucher', 200);
        }

        $couponId = (int) $request->input('coupon_id', 0);
        if ($couponId <= 0) {
            return errValidator('Voucher không hợp lệ', 200);
        }

        $ok = $this->couponService->save($userId, $couponId);
        if (! $ok) {
            return errValidator('Voucher không tồn tại', 200);
        }

        return successNoData('Đã lưu voucher');
    }

    public function unsave(Request $request): JsonResponse
    {
        $userId = (int) getCurrentUserId();
        if ($userId <= 0) {
            return errValidator('Vui lòng đăng nhập', 200);
        }

        $couponId = (int) $request->input('coupon_id', 0);
        $this->couponService->unsave($userId, $couponId);

        return successNoData('Đã bỏ lưu');
    }

    public function remove(Request $request): JsonResponse
    {
        session()->forget(getCoreConfig('session.applied_coupons'));

        return successData('Success', $this->renderState($request, $this->contextHasShipping($request)));
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
        $items     = $this->cart->getItems();
        $subtotal  = (int) $this->cart->getSubtotal();

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
