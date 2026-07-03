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
use App\Services\CartService;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Checkout\CheckoutPromotions;
use App\Services\Checkout\CheckoutTotalService;
use App\Services\Checkout\CreateOrderService;
use App\Services\Checkout\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutTotalService $totalService,
        protected CreateOrderService $createOrderService,
        protected CheckoutPaymentService $paymentService,
        protected CarrierRepositoryInterface $carrierRepo,
        protected PaymentRepositoryInterface $paymentRepo,
        protected OrderRepositoryInterface $orderRepo,
        protected PromotionService $promotionService,
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

        $appliedPromotions = $this->buildAppliedPromotions(hasShipping: true);
        [$error, $items] = $this->validateCart($appliedPromotions);
        $this->syncCartHeader($items);
        [$totalData, $total] = $this->totalService->build($appliedPromotions, withShipping: true);

        $userEmail = auth()->check() ? (string) auth()->user()->email : '';
        $promo = $this->promotionService->viewData($appliedPromotions, (int) $this->cartService->getSubtotal(), $userEmail, hasShipping: true);

        return $this->render('web::checkout.index', [
            'carriers'           => $this->carrierRepo->listAllCached(),
            'payments'           => $this->paymentRepo->listAllCached(),
            'error'              => $error,
            'products'           => array_values($items),
            'totalData'          => $totalData,
            'total'              => $total,
            'countProduct'       => $this->cartService->countItems(),
            'coupons'            => $promo['coupons'],
            'appliedCouponCodes' => (array) session()->get(getCoreConfig('session.applied_coupons'), []),
            'couponContext'      => 'checkout',
            'gifts'              => $promo['gifts'],
            'giftItems'          => $promo['giftItems'],
            'myVouchers'         => $promo['myVouchers'],
            'appliedVoucherCodes' => $promo['appliedVoucherCodes'],
        ]);
    }

    public function cart()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_cart', 'seo_description_cart');

        if (filled(request()->get('quantity'))) {
            foreach ((array) request()->get('quantity') as $key => $value) {
                $this->cartService->update((string) $key, (int) $value);
            }
            $this->syncCartHeader();

            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessUpdateCart'));
        }

        if (request()->has('remove')) {
            $this->cartService->remove((string) request()->get('remove'));
            $this->syncCartHeader();

            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessUpdateCart'));
        }

        $error = '';
        $items = [];
        $totalData = [];
        $total = 0;
        $promotions = $this->buildAppliedPromotions(hasShipping: false);
        if ($this->cartService->hasItems()) {
            [$error, $items] = $this->validateCart($promotions);
            [$totalData, $total] = $this->totalService->build($promotions, withShipping: false);
        }
        $this->syncCartHeader($items);

        $effectiveCodes = array_map(fn ($a) => (string) $a['coupon']->code, $promotions->appliedCoupons);

        $userEmail = auth()->check() ? (string) auth()->user()->email : '';
        $promo = $this->promotionService->viewData(
            $promotions,
            (int) $this->cartService->getSubtotal(),
            $userEmail,
            hasShipping: false,
        );

        return $this->render('web::checkout.cart', [
            'error'              => $error,
            'products'           => array_values($items),
            'totalData'          => $totalData,
            'total'              => $total,
            'countProduct'       => $this->cartService->countItems(),
            'coupons'            => $promo['coupons'],
            'appliedCouponCodes' => $effectiveCodes,
            'couponContext'      => 'cart',
            'gifts'              => $promo['gifts'],
            'giftItems'          => $promo['giftItems'],
            'myVouchers'         => $promo['myVouchers'],
            'appliedVoucherCodes' => $promo['appliedVoucherCodes'],
        ]);
    }

    public function addToCart(CheckoutAddToCartRequest $request): JsonResponse
    {
        $params = $request->validated();

        $product = $this->productRepo->findAddableToCart((int) $params['product_id']);
        if (! $product) {
            return respondNotFound(trans('messages.ErrorNotFoundProduct'));
        }

        $result = $this->cartService->tryAdd([
            'quantity' => $params['quantity'] ?? 1,
            'option'   => $params['option'] ?? [],
        ], $product);

        if (! ($result['ok'] ?? false)) {
            return respondUnprocessable($this->buildAddToCartError($product, $result));
        }
        $this->syncCartHeader();

        $desc = $product->description;
        $name = (string) ($desc->name ?? '');
        $slug = resolveSlug($desc->slug ?? null, $name);

        return respondCreated([
            'notice' => sprintf(
                trans('messages.TextAddCartSuccess'),
                buildUrl($slug, getModuleConfig('url.product'), (int) $product->id),
                $name
            ),
            'cart' => [
                'count' => (int) session()->get(getCoreConfig('session.cart_header'), 0),
                'url'   => route('checkout.cart'),
            ],
        ], trans('messages.SuccessAddCart'));
    }

    protected function buildAddToCartError($product, array $result): string
    {
        if (! empty($result['variant_error'])) {
            return trans('messages.ErrorVariantNotFound');
        }

        $name = $product->description->name ?? '';
        $available = (int) ($result['available'] ?? 0);
        $totalInCart = (int) ($result['total_in_cart'] ?? 0);
        $quantity = (int) ($result['quantity'] ?? 0);
        $totalWanted = $totalInCart + $quantity;

        if ($available <= 0) {
            return sprintf(trans('messages.ErrorStockProduct'), $name);
        }
        if ($totalInCart > 0) {
            return sprintf(trans('messages.TextAddToCartError'), $name, $totalInCart, $quantity, $totalWanted, $available);
        }
        return sprintf(trans('messages.TextAddToCartError2'), $name, $quantity, $available);
    }

    public function consultSign(CheckoutAddToCartRequest $request): JsonResponse
    {
        $params = $request->validated();

        $product = $this->productRepo->resetModel()
            ->where('id', $params['product_id'])
            ->dateAvailable()
            ->with('description')
            ->first();
        if (! $product) {
            return respondNotFound(trans('messages.ErrorNotFoundProduct'));
        }

        $desc = $product->description;
        $name = (string) ($desc->name ?? '');
        $slug = resolveSlug($desc->slug ?? null, $name);

        dispatch(new ConsultSignEmailToAdmin($product, $params, $params['option'] ?? []));

        return respondAccepted([
            'notice' => sprintf(
                trans('messages.TextConsultSignSuccess'),
                buildUrl($slug, getModuleConfig('url.product'), (int) $product->id),
                $name
            ),
        ], trans('messages.SuccessConsultSign'));
    }

    public function saveOrder(CheckoutSaveOrderRequest $request)
    {
        $ctx = $this->buildAppliedPromotions();
        [$error, $items] = $this->validateCart($ctx);
        if ($error !== '' || empty($items)) {
            return redirect(route('checkout.index'))->with('failed', $error ?: trans('messages.ErrorProduct'));
        }

        [$totalData, $total] = $this->totalService->build($ctx, withShipping: true);

        try {
            $orderId = $this->createOrderService->create($ctx, $request->validated(), $totalData, $total);

            $params = $request->validated();
            $this->sendOrderEmails($items, $totalData, $this->buildOrderMailData($params, $orderId));

            $payload = $this->paymentService->buildOrderPayload(
                $params['payment_code'],
                $orderId,
                $total,
                $params['telephone'] ?? null,
                $params['email'] ?? null
            );
            $url = $this->paymentService->startPayment($orderId, $payload);

            $this->cartService->clear();
            session()->put(getCoreConfig('session.last_order'), $orderId);

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

    public function recalcTotals()
    {
        $ctx = $this->buildAppliedPromotions();
        $this->validateCart($ctx);
        [$totalData] = $this->totalService->build($ctx, withShipping: true);

        return respondSuccess($totalData);
    }

    public function success()
    {
        $appTransId = (string) request()->get('apptransid', '');
        if (filled($appTransId)) {
            $this->paymentService->processRedirect(request()->all(), $appTransId);

            return redirect(route('checkout.success'));
        }

        $entity = $this->orderRepo->getOrderSummary((int) session()->get(getCoreConfig('session.last_order'), 0));
        if (! $entity) {
            return redirect(route('home'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.checkout_success'), 'href' => route('checkout.success'), 'separator' => true]);
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_checkout_success', 'seo_description_checkout_success');

        return $this->render('web::checkout.success', [
            'entity' => $entity,
        ]);
    }

    protected function buildAppliedPromotions(bool $hasShipping = true): CheckoutPromotions
    {
        return $this->promotionService->resolveAppliedPromotions(
            $this->cartService->getItems(),
            (int) $this->cartService->getSubtotal(),
            $hasShipping,
        );
    }

    protected function validateCart(CheckoutPromotions $promotions): array
    {
        if (! $this->cartService->hasItems()) {
            return [trans('messages.ErrorProduct'), []];
        }

        $items = $promotions->items;

        if (getConfigDb('config_stock_checkout') && ! $this->cartService->hasStock()) {
            return [trans('messages.ErrorStock'), $items];
        }

        $minimumViolation = $this->cartService->validateMinimum();
        if ($minimumViolation) {
            return [sprintf(trans('messages.ErrorMinimum'), $minimumViolation['name'], $minimumViolation['minimum']), $items];
        }

        return ['', $items];
    }

    protected function syncCartHeader(?array $items = null): void
    {
        $count = $items === null ? $this->cartService->countItems() : array_sum(array_column($items, 'quantity'));
        if ((int) session()->get(getCoreConfig('session.cart_header'), -1) !== $count) {
            session()->put(getCoreConfig('session.cart_header'), $count);
        }
    }

    protected function sendOrderEmails(array $items, array $totalData, array $mailData): void
    {
        if (filled($mailData['email'] ?? null)) {
            dispatch(new OrderCreateSendEmailJob($items, $totalData, $mailData));
        }
        if (filled(getConfigDb('config_email_notification'))) {
            dispatch(new OrderCreateSendEmailToAdminJob($items, $totalData, $mailData));
        }
    }

    protected function buildOrderMailData(array $params, int $orderId): array
    {
        $status = OrdersStatus::where('id', getConfigDb('order_status_id'))
            ->forLocale()
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
