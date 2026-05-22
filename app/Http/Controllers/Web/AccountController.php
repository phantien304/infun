<?php

namespace App\Http\Controllers\Client\InfunStudio;

use App\Helpers\ZaloPay;
use App\Http\Controllers\Client\InfunStudio\Traits\CheckoutPayment;
use App\Model\Entities\Orders;
use App\Model\Entities\OrdersCancel;
use App\Model\Entities\OrdersHistory;
use App\Model\Entities\UserWishlist;
use App\Repositories\Client\InfunStudio\OrderRepository;
use App\Repositories\Client\InfunStudio\UserAddressRepository;
use App\Repositories\Client\InfunStudio\UserPhoneRepository;
use App\Repositories\Client\InfunStudio\UserRepository;
use App\Repositories\Client\InfunStudio\UserWishlistRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountController extends BaseInfunStudioController
{
    protected $_zaloPay;
    use CheckoutPayment;

    public function __construct(UserRepository $userRepository,
                                UserPhoneRepository $userPhoneRepository,
                                UserAddressRepository $userAddressRepository,
                                OrderRepository $orderRepository)
    {
        parent::__construct();
        $this->setRepository($userRepository);
        $this->registerRepository(
            $userPhoneRepository,
            $userAddressRepository,
            $orderRepository
        );
        $this->_zaloPay = app()->make(ZaloPay::class);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account'), 'href' => route('account.index'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->_processMetaSeo('_buildForSeoByConfig', 'account.index.title', 'account.index.description');

        return $this->render('client.infunstudio.account.index');
    }

    public function edit()
    {
        $data = $this->_getParams();
        $user = Auth::user();

        if ($this->_isPOST()) {
            $validator = $this->getRepository()->getValidator();
            if (!$validator->validateUpdateUser($data)) {
                return back()->withErrors($validator->errorsBag()->getMessages())->withInput();
            }
            DB::beginTransaction();
            try {
                $user = $this->getRepository()->where('id', $user->id)->firstOrNew();
                $user->fill([
                    'address' => request()->get('address'),
                    'full_name' => request()->get('full_name'),
                    'sex' => request()->get('sex'),
                ])->save();

                $userPhone = $this->fetchRepository(UserPhoneRepository::class)
                    ->where('user_id', $user->id)
                    ->firstOrNew();

                $verify = 0;
                if ($userPhone->exists && array_get($userPhone, 'is_verify')) {
                    $verify = 1;
                }
                $userPhone->fill([
                    'user_id' => $user->id,
                    'phone' => array_get($data, 'phone'),
                    'is_verify' => $verify
                ])->save();
                DB::commit();
                return redirect(route('account.edit'))->with('success', trans('messages.UpdateSuccess'));
            } catch (\Exception $e) {
                logError($e->getMessage());
                DB::rollback();
            }
            return back()->with('failed', trans('messages.UpdateFailed'))->withInput();
        }
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_edit'), 'href' => '', 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.edit.title', 'account.edit.description');

        return $this->render('client.infunstudio.account.edit', [
            'entity' => $this->getUserInfo(Auth::user())
        ]);
    }

    public function password()
    {
        $data = $this->getParams();
        $user = Auth::user();
        if ($this->_isPOST()) {
            $data = array_merge($data, ['real_password' => $user->password]);
            $validator = $this->getRepository()->getValidator();
            if (!$validator->validateChangeUserPassword($data)) {
                return back()->withErrors($validator->errorsBag()->getMessages())->withInput();
            }
            $user->lockForUpdate();
            $user->fill([
                'password' => Hash::make(array_get($data, 'password')),
            ])->save();
            return back()->with('success', trans('messages.UpdateSuccess'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_password'), 'href' => '', 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.password.title', 'account.password.description');

        return $this->render('client.infunstudio.account.password');
    }

    public function address()
    {
        if (request()->has('remove')) {
            try {
                $this->fetchRepository(UserAddressRepository::class)
                    ->where('user_id', Auth::user()->id)
                    ->where('id', request()->get('remove'))
                    ->delete();
                $this->setCookieUserAddress($this->getUserAddress());
                return redirect(route('account.address'))->with('success', trans('messages.DeleteSuccess'));
            } catch (\Exception $e) {
                logError($e->getMessage());
                return redirect(route('account.address'))->with('failed', trans('messages.DeleteFailed'));
            }
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_address'), 'href' => '', 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.address.title', 'account.address.description');

        $entities = $this->fetchRepository(UserAddressRepository::class)
            ->where('user_id', Auth::user()->id)
            ->with([
                'country',
                'zone.zoneDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                },
                'district.districtDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                },
                'ward.wardDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                },
            ])
            ->get();
        return $this->render('client.infunstudio.account.address', [
            'entities' => $entities,
        ]);
    }

    public function cancelOrder()
    {
        $data = $this->getParams();
        $orderRepository = app()->make(OrderRepository::class);
        $validator = $orderRepository->getValidator();
        if (!$validator->validateCancelOrder($data)) {
            return back()->withErrors($validator->errorsBag()->getMessages())->withInput();
        }
        $zaloPay = app()->make(ZaloPay::class);
        DB::beginTransaction();
        try {
            $order = $orderRepository
                ->where('user_id', getUserLoginId())
                ->where('id', $data['order_id'])
                ->first();
            $refundId = $order->zp_refund_id;
            if (filled($order->zp_trans_id) && empty($refundId)) {
                $refundData = $zaloPay->buildRefundData([
                    'zp_trans_id' => $order->zp_trans_id,
                    'amount' => $order->total,
                    'description' => $data['return_reason']
                ]);
                $response = $zaloPay->refund($refundData);
                if ($response['return_code'] === 2) {
                    return back()->with('failed', trans('messages.RefundFailed'))->withInput();
                }
                $refundId = $response['refund_id'];
            }

            $order->fill([
                'order_status_id' => getConfigDb('order_cancel_status_id'),
                'zp_refund_id' => $refundId,
            ])->save();
            OrdersHistory::create([
                'order_id' => $data['order_id'],
                'order_status_id' => getConfigDb('order_cancel_status_id'),
                'user_id' => getUserLoginId(),
            ]);
            OrdersCancel::create([
                'order_id' => $data['order_id'],
                'user_id' => getUserLoginId(),
                'return_reason' => $data['return_reason'],
                'comment' => array_get($data, 'comment')
            ]);
            DB::commit();
            return back()->with('success', trans('messages.CancelSuccess'));
        } catch (\Exception $e) {
            logError($e->getMessage());
            DB::rollback();
        }
        return back()->with('failed', trans('messages.UpdateFailed'))->withInput();
    }

    public function detailOrder($id)
    {
        $params = $this->getParams();
        $appTransId = array_get($params, 'apptransid', '');
        if (filled($appTransId)) {
            $this->_processUrlPaymentRedirect($params, $appTransId);
            return redirect(route('account.detailOrder', ['id' => $id]))->with('success', trans('messages.PaymentOrderSuccess'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_orders_history'), 'href' => route('account.orders'), 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_order_detail'), 'href' => '', 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.detail_order.title', 'account.detail_order.description');

        $entity = Orders::where('user_id', getUserLoginId())
            ->where('id', $id)
            ->with([
                'ordersProducts.ordersProductOptions',
                'ordersProducts.product' => function ($q) {
                    $q->dateAvailable();
                },
                'ordersProducts.product.productDescription' => function ($q) {
                    $q->languageCode();
                },
                'ordersTotals' => function ($q) {
                    $q->orderBy('sort_order', 'ASC');
                },
                'carrier',
                'payment.paymentDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                },
            ])
            ->orderBy('created_at', 'DESC')
            ->first();

        if (empty($entity)) {
            return redirect(route('account.orders'))->with('failed', trans('messages.ErrorAction'));
        }

        $message = '';
        if (filled($entity->zp_refund_id)) {
            $message = trans('messages.RefundSuccess');
            $refundStatus = app()->make(ZaloPay::class)->getRefundStatus($entity->zp_refund_id);
            $returnCode = $refundStatus['return_code'];
            if ($returnCode == 2 || $returnCode == 3) {
                $message = trans('messages.RefundProcess');
            }
        }

        return $this->render('client.infunstudio.account.order_detail', [
            'entity' => $entity,
            'message' => $message,
        ]);
    }

    public function addressForm()
    {
        $data = $this->getParams();
        $user = Auth::user();
        if ($this->_isPOST()) {
            $userAddressRepository = $this->fetchRepository(UserAddressRepository::class);
            $validator = $userAddressRepository->getValidator();
            if (!$validator->validateAddress($data)) {
                return back()->withErrors($validator->errorsBag()->getMessages())->withInput();
            }
            DB::beginTransaction();
            try {
                $isDefault = request()->get('is_default') ? 1 : 0;
                if ($isDefault) {
                    $userAddressRepository
                        ->where('user_id', $user->id)
                        ->update(['is_default' => 0]);
                }
                $userAddress = $userAddressRepository->where('id', array_get($data, 'id'))->firstOrNew();
                $userAddress->fill([
                    'user_id' => $user->id,
                    'full_name' => request()->get('full_name'),
                    'telephone' => request()->get('telephone'),
                    'country_id' => 230,
                    'zone_id' => request()->get('zone_id'),
                    'district_id' => request()->get('district_id'),
                    'ward_id' => request()->get('ward_id'),
                    'address' => request()->get('address'),
                    'is_default' => $isDefault
                ])->save();
                $this->setCookieUserAddress($this->getUserAddress());
                DB::commit();
                $redirectUrl = array_get($data, 'redirect_url') ?? route('account.address');
                return redirect($redirectUrl)->with('success', trans('messages.UpdateSuccess'));
            } catch (\Exception $e) {
                logError($e->getMessage());
                DB::rollback();
            }
            return back()->with('failed', trans('messages.UpdateFailed'))->withInput();
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_address'), 'href' => '', 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_address_form'), 'href' => '', 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.address_form.title', 'account.address_form.description');

        $entity = $this->fetchRepository(UserAddressRepository::class)
            ->where('user_id', Auth::user()->id)
            ->where('id', array_get($data, 'address_id'))
            ->firstOrNew();

        return $this->render('client.infunstudio.account.address_form', [
            'entity' => $entity
        ]);
    }

    public function wishList()
    {
        $wishListRepository = app()->make(UserWishlistRepository::class);
        if (request()->has('remove')) {
            try {
                $wishListRepository
                    ->where('user_id', Auth::user()->id)
                    ->where('product_id', request()->get('remove'))
                    ->delete();
                $this->setSessionUserTotalWishlist($this->getUserTotalWishlist());
                return redirect(route('account.wishlist'))->with('success', trans('messages.DeleteSuccess'));
            } catch (\Exception $e) {
                logError($e->getMessage());
                return redirect(route('account.wishlist'))->with('failed', trans('messages.DeleteFailed'));
            }
        }
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_wishlist'), 'href' => '', 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.wishlist.title', 'account.wishlist.description');

        $entities = $wishListRepository->where('user_id', Auth::user()->id)
            ->with([
                'product.productDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                },
                'product.productSpecials' => function ($q) {
                    $q->where(function ($qStart) {
                        $qStart->where('date_start', '<', Carbon::now())
                            ->orWhereNull('date_start');
                    })->where(function ($qEnd) {
                        $qEnd->where('date_end', '>', Carbon::now())
                            ->orWhereNull('date_end');
                    })->orderBy('priority');
                },
                'product.stockStatus'
            ])->get();
        if (count($entities) != session()->get(getCoreConfig('session.total_wishlist'), 0)) {
            session()->put(getCoreConfig('session.total_wishlist'), count($entities));
        }
        return $this->render('client.infunstudio.account.wishlist', [
            'entities' => $entities,
        ]);
    }

    public function userWishlist()
    {
        $productId = request()->get('product_id', 0);
        $userWishlist = UserWishlist::where('user_id', getUserLoginId())
            ->where('product_id', $productId)
            ->firstOrNew();
        if ($userWishlist->exists) {
            $userWishlist->delete();
            $total = $this->getUserTotalWishlist();
            $this->setSessionUserTotalWishlist($total);
            return successData(trans('messages.DeleteWishlistSuccess'), ['delete' => true, 'total' => $total]);
        } else {
            $userWishlist->create([
                'user_id' => auth()->user()->id,
                'product_id' => $productId
            ]);
            $total = $this->getUserTotalWishlist();
            $this->setSessionUserTotalWishlist($total);
            return successData(trans('messages.AddWishlistSuccess'), ['delete' => false, 'total' => $total]);
        }
    }

    public function orders()
    {
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_orders_history'), 'href' => '', 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.orders.title', 'account.orders.description');

        $entities = $this->fetchRepository(OrderRepository::class)->getListForFrontend($this->getParams());

        return $this->render('client.infunstudio.account.orders', [
            'entities' => $entities,
        ]);
    }

    public function newsletter()
    {
        $user = Auth::user();
        if ($this->_isPOST()) {
            $user->fill([
                'newsletter' => request()->get('newsletter')
            ])->save();
            return redirect(route('account.newsletter'))->with('success', trans('messages.UpdateSuccess'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.account_orders_newsletter'), 'href' => route('account.newsletter'), 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.newsletter.title', 'account.newsletter.description');

        return $this->render('client.infunstudio.account.newsletter', [
            'entity' => $user,
        ]);
    }

    public function checkVerifyPhone()
    {
        $userPhone = $this->fetchRepository(UserPhoneRepository::class)->getUserPhoneByUserId();
        return successData('CheckSuccess', $userPhone);
    }

    public function logout()
    {
        $this->_removeCookieSessionUser();
        return redirect(route('auth.login'));
    }

    public function addAddress()
    {
        $data = $this->getParams();
        $validator = $this->getRepository()->getValidator();
        if (!$validator->validateAddAddress($data)) {
            $this->removeCookieAddressVisitor();
            return back()->withErrors($validator->errorsBag()->getMessages())->withInput();
        }

        $fullAddress = implode(', ', [
            array_get($data, 'address'),
            array_get($data, 'ward_name'),
            array_get($data, 'district_name'),
            array_get($data, 'zone_name')
        ]);
        $userAddress[] = array_merge($data, [
            'full_address' => $fullAddress,
            'is_default' => 1
        ]);
        $cookieAddress = getCoreConfig('cookie.user.address');
        if (auth()->check()) {
            $userAddress = json_decode(getCookie($cookieAddress, '[]'));
            if (empty($userAddress)) {
                $userAddress = json_decode(json_encode($this->getUserAddress()));
            }
            foreach ($userAddress as $i => $item) {
                $item->is_default = 0;
                if ($item->id == array_get($data, 'id', 0)) {
                    $item->is_default = 1;
                }
            }
        }
        Cookie::queue(getCoreConfig('cookie.user.address'), json_encode($userAddress), getCoreConfig('cookie.time'));
        return back();
    }

    protected function _buildOrdersProducts($products)
    {
        $data = [];
        foreach ($products as $product) {
            $ordersProductOptions = array_get($product, 'orders_product_options');
            $optionData = [];
            foreach ($ordersProductOptions as $option) {
                $variation = array_get($option, 'variation', 2);
                $optChild = null;
                if ($variation == 1) {
                    $children = unserialize(array_get($option, 'children'));
                    $childName = '';
                    $childValue = [];
                    foreach ($children as $child) {
                        $childName = $child['name'];
                        $childValue[] = $child['value'];
                    }
                    $optChild = ['name' => $childName, 'value' => implode(', ', $childValue)];
                }
                $optionData[] = [
                    "id" => array_get($product, 'id'),
                    "name" => array_get($option, 'name'),
                    "order_id" => array_get($option, 'order_id'),
                    "order_product_id" => array_get($option, 'order_product_id'),
                    "product_option_id" => array_get($option, 'product_option_id'),
                    "product_option_value_id" => array_get($option, 'product_option_value_id'),
                    "value" => array_get($option, 'value'),
                    "children" => $optChild,
                    "type" => array_get($option, 'type'),
                    "variation" => $variation,
                ];
            }
            $data[] = [
                "id" => array_get($product, 'id'),
                "model" => array_get($product, 'model'),
                "name" => array_get($product, 'name'),
                "order_id" => array_get($product, 'order_id'),
                "price" => number_format(array_get($product, 'price', 0), 0, '', ',') . 'đ',
                "product_id" => array_get($product, 'product_id'),
                "quantity" => array_get($product, 'quantity'),
                "reward" => array_get($product, 'reward'),
                "tax" => array_get($product, 'tax'),
                "total" => number_format(array_get($product, 'total', 0), 0, '', ',') . 'đ',
                "options" => $optionData
            ];
        }
        return $data;
    }

    protected function _buildOrdersTotals($ordersTotal)
    {
        $data = [];
        foreach ($ordersTotal as $total) {
            $data[] = [
                'title' => $total['title'],
                'value' => number_format($total['value'], 0, '', ',') . 'đ',
            ];
        }
        return $data;
    }
}
