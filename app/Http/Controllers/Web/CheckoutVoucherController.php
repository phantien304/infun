<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Cart\VoucherService;
use App\Services\CartService;
use App\Services\Checkout\CheckoutTotalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AJAX endpoints cho voucher (gift card cá nhân).
 *
 * Routes:
 *   POST /checkout/vouchers/apply   → nhập mã + áp
 *   POST /checkout/vouchers/remove  → bỏ 1 mã cụ thể (hoặc tất cả nếu code trống)
 */
class CheckoutVoucherController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected VoucherService $voucherService,
        protected CheckoutTotalService $totalService,
    ) {}

    public function apply(Request $request): JsonResponse
    {
        $code = (string) $request->input('code', '');
        $orderTotal = $this->estimateOrderTotal();
        $result = $this->voucherService->applyCode($code, $orderTotal);

        if (! $result['ok']) {
            return errValidator($result['message'], 200);
        }

        return successData('Success', [
            'applied_codes' => $this->voucherService->getAppliedCodes(),
            'reload'        => true,
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        $code = (string) $request->input('code', '');
        if ($code === '') {
            $this->voucherService->clearAll();
        } else {
            $this->voucherService->removeCode($code);
        }

        return successData('Success', [
            'applied_codes' => $this->voucherService->getAppliedCodes(),
            'reload'        => true,
        ]);
    }

    /**
     * Voucher tính residual theo orderTotal đang dự kiến (subtotal - coupon
     * discount). Service applyCode dùng giá trị này validate.
     */
    private function estimateOrderTotal(): int
    {
        return (int) $this->cart->getSubtotal();
    }
}
