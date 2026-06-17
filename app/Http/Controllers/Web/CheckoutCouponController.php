<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\CouponDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Services\Cart\CouponService;
use App\Services\CartService;
use App\Services\Checkout\CheckoutContext;
use App\Services\Checkout\CheckoutTotalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint AJAX cho modal Shopee-style "Chọn Voucher".
 *
 * Multi-coupon: lưu danh sách mã đã áp ở session `checkout.applied_coupons`
 * (array<string>). Stacking rule do CouponService::applyCodes quyết định —
 * 1 discount (percent|fixed) winning + 1 freeship (theo config). KHÔNG còn
 * dùng session legacy `coupon` (single string).
 *
 * Routes:
 *   GET  /checkout/coupons        → list
 *   POST /checkout/coupons/apply  → apply (codes[])
 *   POST /checkout/coupons/save   → bookmark
 *   POST /checkout/coupons/unsave → unbookmark
 *   POST /checkout/coupons/remove → clear applied
 */
class CheckoutCouponController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CouponService $couponService,
        protected CouponRepositoryInterface $couponRepo,
        protected CheckoutTotalService $totalService,
    ) {
    }

    /**
     * GET — JSON list voucher cho modal. Bao gồm cờ applicable + saved.
     */
    public function list(Request $request): JsonResponse
    {
        $items     = $this->cart->getItems();
        $subtotal  = $this->cart->getSubtotal();
        $userId    = (int) getCurrentUserId() ?: null;
        $userGroup = getUserGroupId() ?: null;
        $hasShipping = $this->contextHasShipping($request);

        $coupons = $this->couponService->listForCart(
            $items,
            (int) $subtotal,
            $userId,
            $userGroup,
            contextHasShipping: $hasShipping,
        );

        return successData('SearchSuccess', [
            'coupons'       => $coupons->all(),
            'applied_codes' => $this->getAppliedCodes(),
        ], $coupons->count());
    }

    /**
     * POST — user check vài codes trên modal + Submit.
     *
     * Input: `codes[]` array hoặc `code` single string.
     * Server pick:
     *   - 1 discount winning (lớn nhất)
     *   - 1 freeship (nếu config allow_stack)
     * Lưu các mã thắng vào session `checkout.applied_coupons` (array<string>)
     * — CheckoutTotalService::build đọc qua đó.
     */
    public function apply(Request $request): JsonResponse
    {
        $codes = (array) ($request->input('codes') ?? [$request->input('code')]);
        $codes = array_values(array_filter(array_map('strval', $codes), fn ($c) => trim($c) !== ''));

        if (empty($codes)) {
            return errValidator('Vui lòng chọn voucher', 200);
        }
        $items     = $this->cart->getItems();
        $subtotal  = $this->cart->getSubtotal();
        $userId    = (int) getCurrentUserId() ?: null;
        $userGroup = getUserGroupId() ?: null;
        $hasShipping = $this->contextHasShipping($request);

        $result = $this->couponService->applyCodes(
            $codes,
            $items,
            (int) $subtotal,
            $userId,
            $userGroup,
            contextHasShipping: $hasShipping,
        );

        if (empty($result['applied'])) {
            $msg = $result['errors'][0] ?? 'Không có voucher nào áp dụng được';
            return errValidator($msg, 200);
        }

        // Single source of truth — chỉ key `checkout.applied_coupons` array.
        $appliedCodes = array_map(fn ($a) => $a['coupon']->code, $result['applied']);
        session()->put('checkout.applied_coupons', $appliedCodes);

        // Truyền $result đã tính để renderState KHỎI gọi applyCodes lần 2
        // (session vừa set chính là winning codes của $result).
        return successData('Success', $this->renderState($request, $hasShipping, [
            'total_discount' => $result['total_discount'],
            'errors'         => $result['errors'],
        ], $result));
    }

    /**
     * POST — user click "Lưu" trên 1 voucher card → bookmark vào user_coupon.
     */
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

    /**
     * POST — bỏ bookmark.
     */
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

    /**
     * POST — clear toàn bộ coupon đã áp.
     */
    public function remove(Request $request): JsonResponse
    {
        session()->forget('checkout.applied_coupons');

        return successData('Success', $this->renderState($request, $this->contextHasShipping($request)));
    }

    /**
     * @return array<int, string>
     */
    private function getAppliedCodes(): array
    {
        $codes = session()->get('checkout.applied_coupons', []);
        if (! is_array($codes)) {
            return [];
        }
        return array_values(array_filter($codes, 'is_string'));
    }

    /**
     * Resolve whether the calling page already has a shipping fee in its
     * running total. The modal injects a `context` field (`cart` or
     * `checkout`) into every AJAX call — see _coupon_modal.blade.php.
     * Default FALSE if absent: refusing a free-ship coupon is safer than
     * silently accepting one the cart page can't display.
     */
    private function contextHasShipping(Request $request): bool
    {
        return $request->input('context') === 'checkout';
    }

    /**
     * Build state mới (totalData HTML + promo row HTML) sau khi apply/remove
     * để JS thay innerHTML — không reload trang.
     *
     * Merge thêm field từ caller ($extra) vào response payload nếu cần
     * (vd `errors`, `total_discount`).
     */
    private function renderState(Request $request, bool $hasShipping, array $extra = [], ?array $applyResult = null): array
    {
        // Re-resolve ctx mới từ session đã cập nhật.
        $items     = $this->cart->getItems();
        $subtotal  = (int) $this->cart->getSubtotal();
        $userId    = (int) getCurrentUserId() ?: null;
        $userGroup = getUserGroupId() ?: null;

        $ctx = new CheckoutContext();
        $ctx->setItems($items);
        $appliedCodes = (array) session()->get('checkout.applied_coupons', []);
        // apply() đã tính applyCodes và truyền vào $applyResult → khỏi tính lại.
        // remove() không có sẵn → tự resolve từ session.
        if ($applyResult === null && ! empty($appliedCodes)) {
            $applyResult = $this->couponService->applyCodes(
                $appliedCodes, $items, $subtotal, $userId, $userGroup,
                contextHasShipping: $hasShipping,
            );
        }
        if (! empty($applyResult['applied'] ?? [])) {
            $ctx->setAppliedCoupons(
                $applyResult['applied'],
                $applyResult['freeship'],
                $applyResult['total_discount'],
            );
            // applyCodes có thể filter ra coupon không còn hợp lệ (vd cart
            // mutate trong lúc modal mở). Sync lại session để chip strip
            // phản ánh chính xác cái server đang dùng.
            $appliedCodes = array_map(fn ($a) => $a['coupon']->code, $applyResult['applied']);
            session()->put('checkout.applied_coupons', $appliedCodes);
        }

        [$totalData, $total] = $this->totalService->build($ctx, withShipping: $hasShipping);

        // Re-list voucher để promo row hiển thị đúng số mã applicable (số lượng
        // có thể đổi khi user mutate cart).
        $coupons = $this->couponService->listForCart(
            $items, $subtotal, $userId, $userGroup,
            contextHasShipping: $hasShipping,
        );

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
