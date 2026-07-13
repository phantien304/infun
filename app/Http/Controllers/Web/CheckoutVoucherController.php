<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Cart\VoucherService;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutTotalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutVoucherController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected VoucherService $voucherService,
        protected CheckoutTotalService $totalService,
    ) {
    }

    public function apply(Request $request): JsonResponse
    {
        $code = (string) $request->input('code', '');
        $orderTotal = $this->estimateOrderTotal();
        $result = $this->voucherService->applyCode($code, $orderTotal);

        if (! $result['ok']) {
            return respondUnprocessable($result['message']);
        }

        return respondSuccess([
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

        return respondSuccess([
            'applied_codes' => $this->voucherService->getAppliedCodes(),
            'reload'        => true,
        ]);
    }

    private function estimateOrderTotal(): int
    {
        return (int) $this->cartService->getSubtotal();
    }
}
