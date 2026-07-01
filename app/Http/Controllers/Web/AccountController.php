<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\OrderDTO;
use App\Data\Output\UserAddressDTO;
use App\Data\Output\UserDTO;
use App\Data\Output\WishlistItemDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AccountAddAddressRequest;
use App\Http\Requests\Web\AccountAddressRequest;
use App\Http\Requests\Web\AccountCancelOrderRequest;
use App\Http\Requests\Web\AccountChangePasswordRequest;
use App\Http\Requests\Web\AccountUpdateProfileRequest;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Account\AccountService;
use App\Services\Account\AddressService;
use App\Services\Account\WishlistService;
use App\Services\Checkout\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trang account (đã đăng nhập): profile / password / address / wishlist /
 * orders / newsletter.
 *
 * Refactor 2026-05-31 — xem CLAUDE.md mục "Account flow":
 *  - Namespace mới `App\Http\Controllers\Web` (cũ `Client\InfunStudio`).
 *  - Base extends `App\Http\Controllers\Controller` (lazyMap repo + render).
 *  - Service layer mới: AccountService / AddressService / WishlistService.
 *  - RefundService tách khỏi CheckoutPaymentService cho cancel order.
 *  - FormRequest mới thay validator legacy
 *    `OrderValidator::validateCancelOrder` / `UserValidator::validateUpdateUser`
 *    v.v. (validator legacy đã gỡ).
 *  - DTO: UserDTO / UserAddressDTO / WishlistItemDTO / OrderDTO ... thay raw model.
 *  - Helper: `getCurrentUserId()` thay `getUserLoginId()`, `request()->cookie()`
 *    thay `getCookie()`, `processMetaSeo()` thay `_processMetaSeo()`.
 */
class AccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected AddressService $addressService,
        protected WishlistService $wishlistService,
        protected RefundService $refundService,
        protected UserRepositoryInterface $userRepo,
        protected OrderRepositoryInterface $orderRepo,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account'), 'href' => route('account.index'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->processMetaSeo('buildForSeoByConfig', 'account.index.title', 'account.index.description');

        return $this->render('web::account.index');
    }

    // ===== Profile / password / newsletter ============================

    public function edit(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->handleProfileUpdate($request);
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_edit'), 'href' => '', 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.edit.title', 'account.edit.description');

        $user = $this->userRepo->getProfile((int) getCurrentUserId());

        return $this->render('web::account.edit', [
            'entity' => $user ? UserDTO::fromModel($user) : null,
        ]);
    }

    public function password(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->handlePasswordChange($request);
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_password'), 'href' => '', 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.password.title', 'account.password.description');

        $user = $this->userRepo->findById((int) getCurrentUserId());

        return $this->render('web::account.password', [
            'entity' => $user ? UserDTO::fromModel($user) : null,
        ]);
    }

    public function newsletter(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->accountService->updateNewsletter(
                (int) getCurrentUserId(),
                (bool) $request->input('newsletter'),
            );

            return redirect(route('account.newsletter'))->with('success', trans('messages.UpdateSuccess'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_orders_newsletter'), 'href' => route('account.newsletter'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.newsletter.title', 'account.newsletter.description');

        $user = $this->userRepo->findById((int) getCurrentUserId());

        return $this->render('web::account.newsletter', [
            'entity' => $user ? UserDTO::fromModel($user) : null,
        ]);
    }

    // ===== Address =====================================================

    public function address(Request $request)
    {
        if ($request->has('remove')) {
            $ok = $this->addressService->delete((int) getCurrentUserId(), (int) $request->get('remove'));

            return redirect(route('account.address'))
                ->with($ok ? 'success' : 'failed', trans($ok ? 'messages.DeleteSuccess' : 'messages.DeleteFailed'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_address'), 'href' => '', 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.address.title', 'account.address.description');

        $addresses = $this->addressService->listForUser((int) getCurrentUserId());

        return $this->render('web::account.address', [
            'entities' => UserAddressDTO::collect($addresses),
        ]);
    }

    public function addressForm(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->handleAddressSave($request);
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_address'), 'href' => '', 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_address_form'), 'href' => '', 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.address_form.title', 'account.address_form.description');

        $userId = (int) getCurrentUserId();
        $addressId = (int) $request->get('address_id', 0);
        $entity = $addressId > 0
            ? $this->addressService->findForUser($userId, $addressId)
            : null;

        return $this->render('web::account.address_form', [
            'entity' => $entity ? UserAddressDTO::fromModel($entity) : null,
        ]);
    }

    public function addAddress(AccountAddAddressRequest $request)
    {
        $this->addressService->applyQuickAddress(
            getCurrentUserId(),
            $request->validated(),
        );

        return back();
    }

    // ===== Wishlist ====================================================

    public function wishList(Request $request)
    {
        $userId = (int) getCurrentUserId();
        if ($request->has('remove')) {
            $ok = $this->wishlistService->remove($userId, (int) $request->get('remove'));

            return redirect(route('account.wishlist'))
                ->with($ok ? 'success' : 'failed', trans($ok ? 'messages.DeleteSuccess' : 'messages.DeleteFailed'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_wishlist'), 'href' => '', 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.wishlist.title', 'account.wishlist.description');

        $items = $this->wishlistService->listForUser($userId);

        return $this->render('web::account.wishlist', [
            'entities' => WishlistItemDTO::collect($items),
        ]);
    }

    public function userWishlist(Request $request): JsonResponse
    {
        $userId = (int) getCurrentUserId();
        $productId = (int) $request->get('product_id', 0);
        if ($productId <= 0) {
            return respondUnprocessable(trans('messages.ErrorNotFoundProduct'));
        }

        $added = $this->wishlistService->toggle($userId, $productId);
        $total = $this->wishlistService->countForUser($userId);

        return respondSuccess(
            ['deleted' => ! $added, 'total' => $total],
            trans('messages.' . ($added ? 'AddWishlistSuccess' : 'DeleteWishlistSuccess')),
        );
    }

    // ===== Orders ======================================================

    public function orders(Request $request)
    {
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_orders_history'), 'href' => '', 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.orders.title', 'account.orders.description');

        $paginator = $this->orderRepo->getListForUser((int) getCurrentUserId(), $request);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($order) => OrderDTO::fromModel($order)),
        );

        return $this->render('web::account.orders', [
            'entities' => $paginator,
        ]);
    }

    public function detailOrder(Request $request, $id)
    {
        $appTransId = (string) $request->get('apptransid', '');
        if (filled($appTransId)) {
            app(\App\Services\Checkout\CheckoutPaymentService::class)
                ->processRedirect($request->all(), $appTransId);

            return redirect(route('account.detailOrder', ['id' => $id]))
                ->with('success', trans('messages.PaymentOrderSuccess'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_orders_history'), 'href' => route('account.orders'), 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_order_detail'), 'href' => '', 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'account.detail_order.title', 'account.detail_order.description');

        $order = $this->orderRepo->getDetailForUser((int) $id, (int) getCurrentUserId());
        if (! $order) {
            return redirect(route('account.orders'))->with('failed', trans('messages.ErrorAction'));
        }

        $message = '';
        if (filled($order->zp_refund_id)) {
            $status = $this->refundService->getStatus((string) $order->zp_refund_id);
            $message = trans(match ($status) {
                'success'    => 'messages.RefundSuccess',
                'processing' => 'messages.RefundProcess',
                default      => 'messages.RefundSuccess',
            });
        }

        return $this->render('web::account.order_detail', [
            'entity'  => OrderDTO::fromModel($order),
            'message' => $message,
        ]);
    }

    public function cancelOrder(AccountCancelOrderRequest $request)
    {
        $data = $request->validated();
        try {
            $order = $this->accountService->cancelOrder(
                (int) $data['order_id'],
                (int) getCurrentUserId(),
                (string) $data['return_reason'],
                $data['comment'] ?? null,
            );
        } catch (\RuntimeException $e) {
            // RefundService trả [false, null] → throw 'refund_failed'.
            if ($e->getMessage() === 'refund_failed') {
                return back()->with('failed', trans('messages.RefundFailed'))->withInput();
            }

            return back()->with('failed', trans('messages.UpdateFailed'))->withInput();
        } catch (\Throwable $e) {
            logError($e);

            return back()->with('failed', trans('messages.UpdateFailed'))->withInput();
        }

        if (! $order) {
            return back()->with('failed', trans('messages.UpdateFailed'))->withInput();
        }

        return back()->with('success', trans('messages.CancelSuccess'));
    }

    // ===== Logout ======================================================

    public function logout()
    {
        $this->addressService->clearVisitorCookie();
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect(route('auth.login'));
    }

    // ===== private helpers ============================================

    protected function handleProfileUpdate(Request $request)
    {
        $validated = app(AccountUpdateProfileRequest::class)->validated();
        $user = $this->accountService->updateProfile((int) getCurrentUserId(), $validated);

        return $user
            ? redirect(route('account.edit'))->with('success', trans('messages.UpdateSuccess'))
            : back()->with('failed', trans('messages.UpdateFailed'))->withInput();
    }

    protected function handlePasswordChange(Request $request)
    {
        $validated = app(AccountChangePasswordRequest::class)->validated();
        $user = $this->accountService->changePassword(
            (int) getCurrentUserId(),
            (string) $validated['password'],
        );

        return $user
            ? back()->with('success', trans('messages.UpdateSuccess'))
            : back()->with('failed', trans('messages.UpdateFailed'))->withInput();
    }

    protected function handleAddressSave(Request $request)
    {
        $validated = app(AccountAddressRequest::class)->validated();
        $this->addressService->save((int) getCurrentUserId(), $validated);

        $redirectUrl = filled($validated['redirect_url'] ?? null)
            ? (string) $validated['redirect_url']
            : route('account.address');

        return redirect($redirectUrl)->with('success', trans('messages.UpdateSuccess'));
    }
}
