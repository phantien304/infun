<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\CouponExhaustedException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\RewardExhaustedException;
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
use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Checkout\CheckoutPromotions;
use App\Services\Checkout\CheckoutTotalService;
use App\Services\Checkout\CreateOrderService;
use App\Services\Checkout\PromotionService;
use App\Services\Stock\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
        protected StockService $stockService,
        protected UserRewardRepositoryInterface $userRewardRepo,
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

        $promotions = $this->buildAppliedPromotions(hasShipping: true);
        [$error, $items] = $this->validateCart($promotions);

        if ($error === '' && ! empty($items)) {
            $reserve = $this->reserveCheckout($items);
            if (! ($reserve['ok'] ?? true)) {
                $failed = $reserve['failed'] ?? [];
                $error = sprintf(trans('messages.ErrorStockProduct'), (string) ($failed['name'] ?? ''));
            }
        }

        $this->syncCartHeader($items);
        [$totalData, $total] = $this->totalService->build($promotions, withShipping: true);

        $userEmail = auth()->check() ? (string) auth()->user()->email : '';
        $promo = $this->promotionService->viewData($promotions, (int) $this->cartService->getSubtotal(), $userEmail, hasShipping: true);

        return $this->render('web::checkout.index', [
            'carriers'           => $this->carrierRepo->listAllCached(),
            'payments'           => $this->paymentRepo->listAllCached(),
            'idempotencyKey'     => $this->currentIdempotencyToken(),
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
            'rewardEnabled'      => getConfigDb('config_reward_point_enabled') != setting('reward_point.disable'),
            'rewardBalance'      => auth()->check() ? $this->userRewardRepo->getTotalPoints((int) getCurrentUserId()) : 0,
            'rewardApplied'      => (int) session()->get(getCoreConfig('session.reward'), 0),
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

        $resultAddCart = $this->cartService->tryAdd([
            'quantity' => $params['quantity'] ?? 1,
            'option'   => $params['option'] ?? [],
        ], $product);

        if (! ($resultAddCart['ok'] ?? false)) {
            return respondUnprocessable($this->buildAddToCartError($product, $resultAddCart));
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

    protected function buildAddToCartError($product, array $resultAddCart): string
    {
        if (! empty($resultAddCart['variant_error'])) {
            return trans('messages.ErrorVariantNotFound');
        }

        $name = $product->description->name ?? '';
        $available = (int) ($resultAddCart['available'] ?? 0);
        $totalInCart = (int) ($resultAddCart['total_in_cart'] ?? 0);
        $quantity = (int) ($resultAddCart['quantity'] ?? 0);
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
        $promotions = $this->buildAppliedPromotions();
        [$error, $items] = $this->validateCart($promotions);
        if ($error !== '' || empty($items)) {
            return redirect(route('checkout.index'))->with('failed', $error ?: trans('messages.ErrorProduct'));
        }

        [$totalData, $total] = $this->totalService->build($promotions, withShipping: true);

        $sessionId = (string) session()->getId();
        $idemKey = (string) $request->input('idempotency_key', '');
        if ($idemKey === '') {
            $idemKey = (string) session()->get(getCoreConfig('session.checkout_idem'), '');
        }
        if ($idemKey === '') {
            $idemKey = $sessionId.'|'.$this->cartSignature($items, $total);
        }
        $idemKeyMd5 = md5($idemKey);
        $idemCacheKey = $this->buildIdempotencyKey($idemKeyMd5);

        if (! Cache::add($idemCacheKey, 'processing', now()->addSeconds(60))) {
            return $this->handleDuplicateSubmit($idemCacheKey, $idemKeyMd5);
        }

        $params = $request->validated();
        $params['idempotency_key'] = $idemKeyMd5;

        try {
            $orderId = $this->createOrderService->create($promotions, $params, $totalData, $total);

            Cache::put($idemCacheKey, $orderId, now()->addSeconds(60));
        } catch (InsufficientStockException $e) {
            Cache::forget($idemCacheKey);
            logError($e);
            return redirect(route('checkout.index'))
                ->with('failed', sprintf(trans('messages.ErrorStockProduct'), ''))
                ->withInput();
        } catch (CouponExhaustedException $e) {
            Cache::forget($idemCacheKey);
            logError($e);
            return redirect(route('checkout.index'))
                ->with('failed', trans(
                    $e->scope === CouponExhaustedException::SCOPE_USER
                        ? 'messages.checkout.coupon.used_up_user'
                        : 'messages.checkout.coupon.used_up_total'
                ))
                ->withInput();
        } catch (RewardExhaustedException $e) {
            Cache::forget($idemCacheKey);
            logError($e);
            return redirect(route('checkout.index'))
                ->with('failed', sprintf(trans('messages.checkout.reward.not_enough'), number_format($e->available)))
                ->withInput();
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                $existing = $this->orderRepo->findByIdempotencyKey($idemKeyMd5);
                if ($existing) {
                    Cache::put($idemCacheKey, $existing->id, now()->addSeconds(60));
                    session()->put(getCoreConfig('session.last_order'), $existing->id);
                    session()->forget(getCoreConfig('session.checkout_idem'));

                    return redirect(route('checkout.success'))->with('success', trans('messages.SuccessCreateOrder'));
                }
            }
            Cache::forget($idemCacheKey);
            logError($e);
            return redirect(route('checkout.index'))->with('failed', trans('messages.ErrorCreateOrder'))->withInput();
        } catch (\Throwable $e) {
            Cache::forget($idemCacheKey);
            logError($e);
            return redirect(route('checkout.index'))->with('failed', trans('messages.ErrorCreateOrder'))->withInput();
        }

        return $this->finalizeCreatedOrder($orderId, $params, $items, $totalData, $total, $sessionId, $promotions);
    }

    protected function finalizeCreatedOrder(int $orderId, array $params, array $items, array $totalData, int $total, string $sessionId, CheckoutPromotions $promotions)
    {
        $warnings = [];
        if (! empty($promotions->droppedGifts)) {
            $warnings[] = sprintf(
                trans('messages.checkout.gift.exhausted'),
                '"'.implode('", "', $promotions->droppedGifts).'"',
            );
        }

        try {
            $this->cartService->clear();
            $this->stockService->releaseHolder($sessionId);
        } catch (\Throwable $e) {
            logError('finalizeCreatedOrder cleanup: '.$e->getMessage(), ['order_id' => $orderId]);
        }

        session()->put(getCoreConfig('session.last_order'), $orderId);
        session()->forget(getCoreConfig('session.checkout_idem'));

        try {
            $this->sendOrderEmails($items, $totalData, $this->buildOrderMailData($params, $orderId));
        } catch (\Throwable $e) {
            logError('sendOrderEmails: '.$e->getMessage(), ['order_id' => $orderId]);
        }

        try {
            $payload = $this->paymentService->buildOrderPayload(
                $params['payment_code'],
                $orderId,
                $total,
                $params['telephone'] ?? null,
                $params['email'] ?? null
            );
            $url = $this->paymentService->startPayment($orderId, $payload);

            if (filled($url)) {
                // Đi gateway — flash warning vẫn sống tới request kế của
                // session (lúc khách quay về trang success/processing).
                return $this->successRedirect($warnings, redirect($url));
            }
        } catch (\Throwable $e) {
            logError('startPayment: '.$e->getMessage(), ['order_id' => $orderId]);
            $warnings[] = trans('messages.checkout.payment_init_failed');
        }

        return $this->successRedirect($warnings);
    }

    /** Về trang success (hoặc redirect chỉ định) kèm gom các cảnh báo không-chặn-đơn. */
    protected function successRedirect(array $warnings, $redirect = null)
    {
        $redirect ??= redirect(route('checkout.success'))->with('success', trans('messages.SuccessCreateOrder'));

        if (! empty($warnings)) {
            $redirect->with('failed', implode('<br>', $warnings));
        }

        return $redirect;
    }

    protected function handleDuplicateSubmit(string $idemCacheKey, string $idemKeyMd5)
    {
        $valueCache = Cache::get($idemCacheKey);

        if (is_numeric($valueCache)) {
            session()->put(getCoreConfig('session.last_order'), (int) $valueCache);

            return redirect(route('checkout.success'))->with('success', trans('messages.SuccessCreateOrder'));
        }

        if ($valueCache === null) {
            return redirect(route('checkout.index'))->with('failed', trans('messages.ErrorCreateOrder'))->withInput();
        }

        session()->put(getCoreConfig('session.checkout_pending'), $idemKeyMd5);

        return redirect(route('checkout.processing'));
    }

    protected function pendingOrderState(): array
    {
        $pendingKey = (string) getCoreConfig('session.checkout_pending');
        $idemKeyMd5 = (string) session()->get($pendingKey, '');

        if ($idemKeyMd5 === '') {
            return ['status' => 'none', 'redirect' => route('checkout.index')];
        }

        $valueCache = Cache::get($this->buildIdempotencyKey($idemKeyMd5));

        if (is_numeric($valueCache)) {
            session()->put(getCoreConfig('session.last_order'), (int) $valueCache);
            session()->forget($pendingKey);

            return ['status' => 'done', 'redirect' => route('checkout.success')];
        }

        if ($valueCache === null) {
            session()->forget($pendingKey);

            return ['status' => 'failed', 'redirect' => route('checkout.index')];
        }

        return ['status' => 'processing', 'redirect' => null];
    }

    public function processing()
    {
        $state = $this->pendingOrderState();

        if ($state['status'] === 'done') {
            return redirect($state['redirect'])->with('success', trans('messages.SuccessCreateOrder'));
        }
        if ($state['status'] === 'failed') {
            return redirect($state['redirect'])->with('failed', trans('messages.ErrorCreateOrder'));
        }
        if ($state['status'] === 'none') {
            return redirect($state['redirect']);
        }

        return view('web::checkout.processing');
    }

    public function orderStatus()
    {
        return respondSuccess($this->pendingOrderState());
    }

    protected function currentIdempotencyToken(): string
    {
        $key = getCoreConfig('session.checkout_idem');
        $token = (string) session()->get($key, '');
        if ($token === '') {
            $token = (string) Str::uuid();
            session()->put($key, $token);
        }

        return $token;
    }

    protected function buildIdempotencyKey(string $idemKeyMd5): string
    {
        return 'checkout:idem:'.$idemKeyMd5;
    }

    protected function isDuplicateKey(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            || (string) $e->getCode() === '23000';
    }

    protected function cartSignature(array $items, int $total): string
    {
        $parts = [];
        foreach ($items as $item) {
            $parts[] = ($item['id'] ?? 0).':'.($item['product_variant_id'] ?? 0).':'.($item['quantity'] ?? 0);
        }
        sort($parts);

        return md5(implode('|', $parts).'#'.$total);
    }

    protected function reserveCheckout(array $items): array
    {
        return $this->stockService->reserveCheckout(
            $items,
            (string) session()->getId(),
            (int) getCurrentUserId() ?: null,
        );
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

        // Snapshot đồng đang chọn để mailer (chạy queue, không cookie) format đúng đồng.
        $currency = $this->currencyService->currentCurrency();

        return array_merge($params, [
            'order_id'       => $orderId,
            'uniqid'         => strtoupper(uniqid()),
            'order_status'   => $status?->name ?? '',
            'payment_name'   => $payment?->description?->name ?? 'Trả tiền khi nhận hàng',
            'currency_code'  => $currency->code,
            'currency_value' => $currency->value,
        ]);
    }
}
