<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CheckoutAddToCartRequest;
use App\Http\Requests\Web\CheckoutSaveOrderRequest;
use App\Http\Requests\Web\CheckoutSaveRepaymentRequest;
use App\Jobs\ConsultSignEmailToAdmin;
use App\Jobs\OrderCreateSendEmailJob;
use App\Jobs\OrderCreateSendEmailToAdminJob;
use App\Models\Entities\OrdersStatus;
use App\Repositories\Interfaces\CarrierRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\Cart\CouponService;
use App\Services\Cart\GiftService;
use App\Services\Cart\VoucherService;
use App\Services\CartService;
use App\Services\Checkout\CheckoutContext;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Checkout\CheckoutTotalService;
use App\Services\Checkout\CreateOrderService;
use Illuminate\Http\Request;

/**
 * Checkout flow refactor — extend base controller mới (lazyMap repo + breadcrumbs
 * + processMetaSeo).
 *
 * Phân tầng:
 *  - Controller: orchestrate, không tự query model, không tự cache, không tự
 *    transaction. Chỉ inject service/repo qua DI rồi gọi.
 *  - Service (CartService, CheckoutTotalService, CreateOrderService,
 *    CheckoutPaymentService): business logic + transaction.
 *  - Repository (Carrier/Payment/Coupon/Voucher/Order/UserReward): persistence +
 *    cache (theo CacheableRepository trait).
 *
 * Schema mới: Cart đọc product_variant_id resolve qua product_variant_attribute
 * (xem CartService::resolveVariantId). Giá lấy từ ProductVariant.price tuyệt
 * đối, tồn từ product_stock.on_hand - reserved.
 *
 * Blade contract: giữ raw array shape (`$products[i]['name']` etc) để không
 * phá web/checkout/*.blade.php hiện tại.
 */
class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CheckoutTotalService $totalService,
        protected CreateOrderService $createOrderService,
        protected CheckoutPaymentService $paymentService,
        protected CarrierRepositoryInterface $carrierRepo,
        protected PaymentRepositoryInterface $paymentRepo,
        protected OrderRepositoryInterface $orderRepo,
        protected CouponService $couponService,
        protected GiftService $giftService,
        protected VoucherService $voucherService,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.cart'), 'href' => route('checkout.cart'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.checkout'), 'href' => route('checkout.index'), 'separator' => true]);
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_checkout', 'seo_description_checkout');

        $ctx = $this->buildContext(hasShipping: true);
        [$error, $items] = $this->extractItems($ctx);
        $this->syncCartHeader($items);
        [$totalData, $total] = $this->totalService->build($ctx, withShipping: true);

        $coupons = $this->couponService->listForCart(
            $items ?: [],
            (int) $this->cart->getSubtotal(),
            (int) getCurrentUserId() ?: null,
            getUserGroupId() ?: null,
            contextHasShipping: true,
        );
        $gifts = $this->giftService->listForCart($items ?: [], (int) $this->cart->getSubtotal());
        $giftItems = $this->giftService->resolveGiftDisplayItems();
        $userEmail = auth()->check() ? (string) auth()->user()->email : '';
        $myVouchers = $userEmail !== ''
            ? $this->voucherService->listMyVouchers($userEmail, (int) $this->cart->getSubtotal())
            : collect();
        $appliedVoucherCodes = $this->voucherService->getAppliedCodes();

        return $this->render('web::checkout.index', [
            'carriers'           => $this->carrierRepo->listAllCached(),
            'payments'           => $this->paymentRepo->listAllCached(),
            'error'              => $error,
            'products'           => array_values($items),
            'totalData'          => $totalData,
            'total'              => $total,
            'countProduct'       => $this->cart->countItems(),
            'coupons'            => $coupons,
            'appliedCouponCodes' => (array) session()->get('checkout.applied_coupons', []),
            'couponContext'      => 'checkout',
            'gifts'              => $gifts,
            'giftItems'          => $giftItems,
            'myVouchers'         => $myVouchers,
            'appliedVoucherCodes' => $appliedVoucherCodes,
        ]);
    }

    public function cart()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_cart', 'seo_description_cart');

        if (filled(request()->get('quantity'))) {
            foreach ((array) request()->get('quantity') as $key => $value) {
                $this->cart->update((string) $key, (int) $value);
            }
            $this->syncCartHeader();

            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessUpdateCart'));
        }

        if (request()->has('remove')) {
            $this->cart->remove((string) request()->get('remove'));
            $this->syncCartHeader();

            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessUpdateCart'));
        }

        // Bỏ legacy `?coupon=` và `?voucher=` query path — Shopee modal
        // (POST /checkout/coupons/apply) là entrypoint duy nhất. Bookmark
        // chứa query coupon cũ sẽ silently bị ignore.

        $error = '';
        $items = [];
        $totalData = [];
        $total = 0;
        $ctx = null;
        if ($this->cart->hasItems()) {
            $ctx = $this->buildContext(hasShipping: false);
            [$error, $items] = $this->extractItems($ctx);
            [$totalData, $total] = $this->totalService->build($ctx, withShipping: false);
        }
        $this->syncCartHeader($items);

        $coupons = $this->couponService->listForCart(
            $items ?: [],
            (int) $this->cart->getSubtotal(),
            (int) getCurrentUserId() ?: null,
            getUserGroupId() ?: null,
            contextHasShipping: false,
        );

        $effectiveCodes = $ctx
            ? array_map(fn ($a) => (string) $a['coupon']->code, $ctx->appliedCoupons)
            : [];

        $gifts = $this->giftService->listForCart($items ?: [], (int) $this->cart->getSubtotal());
        $giftItems = $this->giftService->resolveGiftDisplayItems();
        $userEmail = auth()->check() ? (string) auth()->user()->email : '';
        $myVouchers = $userEmail !== ''
            ? $this->voucherService->listMyVouchers($userEmail, (int) $this->cart->getSubtotal())
            : collect();
        $appliedVoucherCodes = $this->voucherService->getAppliedCodes();

        return $this->render('web::checkout.cart', [
            'error'              => $error,
            'products'           => array_values($items),
            'totalData'          => $totalData,
            'total'              => $total,
            'countProduct'       => $this->cart->countItems(),
            'coupons'            => $coupons,
            'appliedCouponCodes' => $effectiveCodes,
            'couponContext'      => 'cart',
            'gifts'              => $gifts,
            'giftItems'          => $giftItems,
            'myVouchers'         => $myVouchers,
            'appliedVoucherCodes' => $appliedVoucherCodes,
        ]);
    }

    public function addToCart(CheckoutAddToCartRequest $request)
    {
        $params = $request->validated();

        $product = $this->productRepo->resetModel()
            ->where('id', $params['product_id'])
            ->where('is_add_cart', 1)
            ->dateAvailable()
            ->with('description')
            ->first();
        if (! $product) {
            return errValidator(trans('messages.ErrorNotFoundProduct'), 200);
        }

        $result = $this->cart->tryAdd([
            'id'       => $product->id,
            'quantity' => $params['quantity'] ?? 1,
            'option'   => $params['option'] ?? [],
        ]);

        if (! ($result['ok'] ?? false)) {
            return errValidator(
                $this->buildAddToCartError($product, $result),
                200,
            );
        }
        $this->syncCartHeader();

        return successData('AddSuccess', [
            'success_2'         => sprintf(
                trans('messages.TextAddCartSuccess'),
                method_exists($product, 'getUrlClient') ? $product->getUrlClient() : '#',
                $product->description->name ?? ''
            ),
            'total_cart_header' => session()->get('total_cart_header'),
            'link_cart'         => route('checkout.cart'),
        ]);
    }

    protected function buildAddToCartError($product, array $result): string
    {
        $name = $product->description->name ?? '';

        if (($result['reason'] ?? '') === 'out_of_stock') {
            $available = (int) ($result['available'] ?? 0);
            $already   = (int) ($result['already_in_cart'] ?? 0);
            $requested = (int) ($result['requested'] ?? 0);
            $totalWanted = $already + $requested;

            if ($available <= 0) {
                return sprintf(trans('messages.ErrorStockProduct'), $name);
            }
            if ($already > 0) {
                return sprintf(
                    'Sản phẩm <b style="color: #d81800;">%s</b>: bạn đã có %d trong giỏ, yêu cầu thêm %d (tổng %d) nhưng kho chỉ còn %d.',
                    $name,
                    $already,
                    $requested,
                    $totalWanted,
                    $available,
                );
            }
            return sprintf(
                'Sản phẩm <b style="color: #d81800;">%s</b>: bạn yêu cầu %d nhưng kho chỉ còn %d.',
                $name,
                $requested,
                $available,
            );
        }

        return trans('messages.ErrorNotFoundProduct');
    }

    public function consultSign(CheckoutAddToCartRequest $request)
    {
        $params = $request->validated();

        $product = $this->productRepo->resetModel()
            ->where('id', $params['product_id'])
            ->dateAvailable()
            ->with('description')
            ->first();
        if (! $product) {
            return errValidator(trans('messages.ErrorNotFoundProduct'), 200);
        }

        dispatch(new ConsultSignEmailToAdmin($product, $params, $params['option'] ?? []));

        return successData('SendSuccess', [
            'success_2' => sprintf(
                trans('messages.TextConsultSignSuccess'),
                method_exists($product, 'getUrlClient') ? $product->getUrlClient() : '#',
                $product->description->name ?? ''
            ),
        ]);
    }

    public function saveOrder(CheckoutSaveOrderRequest $request)
    {
        $ctx = $this->buildContext();
        [$error, $items] = $this->extractItems($ctx);
        if ($error !== '' || empty($items)) {
            return redirect(route('checkout.index'))->with('failed', $error ?: trans('messages.ErrorProduct'));
        }

        [$totalData, $total] = $this->totalService->build($ctx, withShipping: true);

        try {
            $orderId = $this->createOrderService->create($ctx, $request->validated(), $totalData, $total);

            $params = $request->validated();
            $this->sendNotifications($items, $totalData, $this->buildMailData($params, $orderId));

            $payload = $this->paymentService->buildOrderPayload(
                $params['payment_code'],
                $orderId,
                $total,
                $params['telephone'] ?? null,
                $params['email'] ?? null
            );
            $url = $this->paymentService->startPayment($orderId, $payload);

            $this->cart->clear();
            session()->put('lastOrderSuccess', $orderId);

            if (filled($url)) {
                return redirect($url);
            }

            return redirect(route('checkout.success'))->with('success', trans('messages.SuccessCreateOrder'));
        } catch (\Throwable $e) {
            logError($e);

            return redirect(route('checkout.index'))->with('failed', trans('messages.ErrorCreateOrder'))->withInput();
        }
    }

    public function saveRepayment(CheckoutSaveRepaymentRequest $request)
    {
        $params = $request->validated();

        $order = $this->orderRepo->getOrderForUser((int) $params['order_id'], (int) getCurrentUserId());
        if (! $order) {
            return back()->with('failed', trans('messages.ErrorRepaymentOrder'))->withInput();
        }

        $code = $params['payment_code'];
        if ($code === 'cod') {
            $this->orderRepo->upsertOrder([
                'id'              => $order->id,
                'payment_code'    => $code,
                'order_status_id' => getConfigDb('order_status_id'),
            ]);

            return redirect()->to(route('account.detailOrder', ['id' => $order->id]))
                ->with('success', trans('messages.UpdateSuccess'));
        }

        $payload = $this->paymentService->buildRepaymentPayload([
            'id'           => $order->id,
            'telephone'    => $order->telephone,
            'email'        => $order->email,
            'total'        => (int) $order->total,
            'payment_code' => $code,
        ]);
        $this->orderRepo->upsertOrder([
            'id'           => $order->id,
            'app_trans_id' => $payload['app_trans_id'] ?? null,
        ]);

        $url = $this->paymentService->startPayment($order->id, $payload);
        if (filled($url)) {
            return redirect($url);
        }

        return back()->with('failed', trans('messages.ErrorRepaymentOrder'))->withInput();
    }

    public function repayment($id)
    {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account'), 'href' => route('account.index'), 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account_orders_history'), 'href' => route('account.orders'), 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account_order_detail'), 'href' => route('account.detailOrder', ['id' => $id]), 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account_repayment'), 'href' => '', 'separator' => true],
        ];
        $this->processMetaSeo('buildForSeoByConfig', 'account.detail_order.title', 'account.detail_order.description');

        $entity = $this->orderRepo->getOrderForUser((int) $id, (int) getCurrentUserId(), recentOnly: true);
        if (! $entity) {
            return redirect(route('account.detailOrder', ['id' => $id]))->with('failed', trans('messages.ErrorAction'));
        }

        return $this->render('web::checkout.repayment', [
            'payments' => $this->paymentRepo->listAllCached(),
            'error'    => '',
            'entity'   => $entity,
        ]);
    }

    public function paymentCallBack(Request $request)
    {
        try {
            $this->paymentService->processCallback($request->getContent());
        } catch (\Throwable $e) {
            logError($e);
        }
    }

    public function shipping()
    {
        $ctx = $this->buildContext();
        $this->extractItems($ctx);
        [$totalData] = $this->totalService->build($ctx, withShipping: true);

        return successData('SearchSuccess', $totalData, 0);
    }

    public function success()
    {
        $appTransId = (string) request()->get('apptransid', '');
        if (filled($appTransId)) {
            $this->paymentService->processRedirect(request()->all(), $appTransId);

            return redirect(route('checkout.success'));
        }

        $entity = $this->orderRepo->getOrderSummary((int) session()->get('lastOrderSuccess', 0));
        if (! $entity) {
            return redirect(route('home'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.checkout_success'), 'href' => route('checkout.success'), 'separator' => true]);
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_checkout_success', 'seo_description_checkout_success');

        return $this->render('web::checkout.success', [
            'entity' => $entity,
        ]);
    }

    // ===== private helpers ============================================

    /**
     * Build context cho 1 request checkout — gồm cart items, coupon, voucher
     * đã resolve. Mọi service downstream nhận context này thay vì tự query.
     */
    /**
     * @param  bool  $hasShipping  Whether the current render path will
     *   include a shipping fee in the running total. Forwarded to
     *   CouponService::applyCodes so free-ship coupons stored in the
     *   session don't appear as "applied" on the cart page where the
     *   discount would be invisible. Default TRUE for safe behaviour at
     *   callers that still build the context for the order-create path.
     */
    protected function buildContext(bool $hasShipping = true): CheckoutContext
    {
        $items = $this->cart->getItems();
        $subtotal = $this->cart->getSubtotal();

        $ctx = new CheckoutContext();
        $ctx->setItems($items);

        // Legacy session('coupon') + session('voucher') + resolveCoupon/resolveVoucher
        // đã bỏ — flow Shopee multi-coupon là single source of truth. Voucher
        // (gift card) sẽ refactor riêng ở phase voucher cluster.

        // Shopee multi-coupon — re-resolve every request so the result
        // reflects post-mutation cart state (e.g. user removed an item and
        // dropped below the coupon's min_subtotal).
        $codes = (array) session()->get('checkout.applied_coupons', []);
        if (! empty($codes)) {
            $applyResult = $this->couponService->applyCodes(
                $codes,
                $items,
                (int) $subtotal,
                (int) getCurrentUserId() ?: null,
                getUserGroupId() ?: null,
                contextHasShipping: $hasShipping,
            );
            $ctx->setAppliedCoupons(
                $applyResult['applied'],
                $applyResult['freeship'],
                $applyResult['total_discount'],
            );
        }

        return $ctx;
    }

    /**
     * Trả [error, items] cho controller. Empty error = OK. Quy tắc:
     *  - cart rỗng → ErrorProduct.
     *  - cart không đủ tồn (config_stock_checkout bật) → ErrorStock.
     *  - vi phạm minimum theo product → ErrorMinimum.
     */
    protected function extractItems(CheckoutContext $ctx): array
    {
        if (! $this->cart->hasItems()) {
            return [trans('messages.ErrorProduct'), []];
        }

        $items = $ctx->items;

        if (getConfigDb('config_stock_checkout') && ! $this->cart->hasStock()) {
            return [trans('messages.ErrorStock'), $items];
        }

        $minimumViolation = $this->cart->validateMinimum();
        if ($minimumViolation) {
            return [sprintf(trans('messages.ErrorMinimum'), $minimumViolation['name'], $minimumViolation['minimum']), $items];
        }

        return ['', $items];
    }

    protected function syncCartHeader(?array $items = null): void
    {
        $count = $items === null ? $this->cart->countItems() : array_sum(array_column($items, 'quantity'));
        if ((int) session()->get('total_cart_header', -1) !== $count) {
            session()->put('total_cart_header', $count);
        }
    }

    protected function sendNotifications(array $items, array $totalData, array $mailData): void
    {
        if (filled($mailData['email'] ?? null)) {
            dispatch(new OrderCreateSendEmailJob($items, $totalData, $mailData));
        }
        if (filled(getConfigDb('config_email_notification'))) {
            dispatch(new OrderCreateSendEmailToAdminJob($items, $totalData, $mailData));
        }
    }

    protected function buildMailData(array $params, int $orderId): array
    {
        $status = OrdersStatus::where('id', getConfigDb('order_status_id'))
            ->where('language_code', app()->getLocale())
            ->first();
        $payment = $this->paymentRepo->findByCode((string) ($params['payment_code'] ?? ''));

        return array_merge($params, [
            'order_id'     => $orderId,
            'uniqid'       => strtoupper(uniqid()),
            'order_status' => $status?->name ?? '',
            'payment_name' => $payment?->description?->name ?? 'Trả tiền khi nhận hàng',
        ]);
    }
}
