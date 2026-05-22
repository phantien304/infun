<?php

namespace App\Http\Controllers\Web;

use App\Helpers\Cart;
use App\Helpers\ZaloPay;
use App\Http\Controllers\Client\InfunStudio\Traits\CheckoutMarketing;
use App\Http\Controllers\Client\InfunStudio\Traits\CheckoutPayment;
use App\Http\Controllers\Client\InfunStudio\Traits\CheckoutTotal;
use App\Http\Controllers\Client\InfunStudio\Traits\CreateOrder;
use App\Http\Controllers\Controller;
use App\Jobs\Client\InfunStudio\ConsultSignEmailToAdmin;
use App\Jobs\Client\InfunStudio\ConsultSignEmailToCustomer;
use App\Jobs\Client\InfunStudio\OrderCreateSendEmailJob;
use App\Jobs\Client\InfunStudio\OrderCreateSendEmailToAdminJob;
use App\Model\Entities\Carrier;
use App\Model\Entities\Orders;
use App\Model\Entities\OrdersHistory;
use App\Model\Entities\OrdersStatus;
use App\Model\Entities\Payment;
use App\Model\Entities\Product;
use App\Repositories\Client\InfunStudio\OrderRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    protected $_cart;
    protected $_zaloPay;
    protected $_error;
    use CheckoutTotal, CheckoutMarketing, CheckoutPayment, CreateOrder;
    const TRANSACTION_REWARD_ON_ORDER = 12;

    public function __construct(OrderRepository $orderRepository)
    {
        parent::__construct();
        $this->setRepository($orderRepository);
        $this->_cart = app()->make(Cart::class);
        $this->_zaloPay = app()->make(ZaloPay::class);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.cart'), 'href' => route('checkout.cart'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->_error = '';

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.checkout'), 'href' => route('checkout.index'), 'separator' => true]);

        $this->_processMetaSeo('_buildForSeoBySetting', 'seo_title_checkout', 'seo_description_checkout');

        $this->_processDataCart();

        $totalData = [];
        $countProduct = $total = $subTotal = 0;
        $productData = $this->_processProductOfCart($subTotal, $total, $countProduct);
        if ($countProduct != session()->get('total_cart_header')) {
            session()->put('total_cart_header', $countProduct);
        }
        $this->getTotalCheckout($totalData, $total);

        $carriers = Carrier::orderBy('sort_order', 'DESC')->get();
        $payments = Payment::with([
            'paymentDescription' => function ($q) {
                $q->where('language_code', app()->getLocale());
            }
        ])
            ->orderBy('sort_order', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        return $this->render('client.infunstudio.checkout.index', [
            'carriers' => $carriers,
            'payments' => $payments,
            'error' => $this->_error,
            'products' => $productData,
            'totalData' => $totalData,
            'total' => $total,
            'countProduct' => $countProduct,
        ]);
    }

    public function repayment($id)
    {
        $this->_error = '';
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account'), 'href' => route('account.index'), 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account_orders_history'), 'href' => route('account.orders'), 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account_order_detail'), 'href' => route('account.detailOrder', ['id' => $id]), 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account_repayment'), 'href' => route('checkout.repayment'), 'separator' => true],
        ];

        $this->_processMetaSeo('_buildForSeoByConfig', 'account.detail_order.title', 'account.detail_order.description');

        $entity = Orders::where('user_id', getUserLoginId())
            ->where('id', $id)
            ->where('created_at', '>', Carbon::now()->subMinutes(240))
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
            return redirect(route('account.detailOrder', ['id' => $id]))->with('failed', trans('messages.ErrorAction'));
        }
        $payments = Payment::with([
            'paymentDescription' => function ($q) {
                $q->where('language_code', app()->getLocale());
            }
        ])
            ->orderBy('sort_order', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        return $this->render('client.infunstudio.checkout.repayment', [
            'payments' => $payments,
            'error' => $this->_error,
            'entity' => $entity,
        ]);
    }

    public function cart()
    {
        $this->_error = '';
        $this->_processMetaSeo('_buildForSeoBySetting', 'seo_title_cart', 'seo_description_cart');

        if (!empty(request()->get('quantity'))) {
            foreach (request()->get('quantity') as $key => $value) {
                $this->_cart->update($key, $value);
            }
            $this->_processTotalCartHeader();
            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessUpdateCart'));
        }

        if (request()->has('remove')) {
            $this->_cart->remove(request()->get('remove'));
            $this->_processTotalCartHeader();
            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessUpdateCart'));
        }

        $this->_processDataCart();
        if (filled(request()->get('coupon', ''))) {
            if (empty($this->getOptions('coupon'))) {
                return redirect(route('checkout.cart'))->with('failed', trans('messages.ErrorCoupon'));
            }
            session()->put('coupon', request()->get('coupon'));
            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessAddCoupon'));
        }
        if (filled(request()->get('voucher', ''))) {
            if (empty($this->getOptions('voucher'))) {
                return redirect(route('checkout.cart'))->with('failed', trans('messages.ErrorVoucher'));
            }
            session()->put('voucher', request()->get('voucher'));
            return redirect(route('checkout.cart'))->with('success', trans('messages.SuccessAddVoucher'));
        }

        $productData = $totalData = [];
        $countProduct = $subTotal = $total = 0;
        if ($this->_cart->hasProducts()) {
            $productData = $this->_processProductOfCart($subTotal, $total, $countProduct);

            $this->getTotalCheckout($totalData, $total, false);
        }
        if ($countProduct != session()->get('total_cart_header')) {
            session()->put('total_cart_header', $countProduct);
        }
        return $this->render('client.infunstudio.checkout.cart', [
            'error' => $this->_error,
            'products' => $productData,
            'totalData' => $totalData,
            'total' => $total,
            'countProduct' => $countProduct,
        ]);
    }

    public function addToCart()
    {
        $params = $this->getParams();
        $options = array_get($params, 'option', []);
        list($product, $errors) = $this->_validateAddToCart($params, $options);

        if (empty($product)) {
            return errValidator(trans('messages.ErrorNotFoundProduct'), 200);
        }

        if (count($errors)) {
            return errValidator($errors, 200);
        }

        $this->_cart->add([
            'id' => $product->id,
            'quantity' => array_get($params, 'quantity', 1),
            'option' => $options
        ]);

        $this->_processTotalCartHeader();

        return successData('AddSuccess', [
            'success_2' => sprintf(trans('messages.TextAddCartSuccess'), $product->getUrlClient(), isset($product->productDescription) ? $product->productDescription->name : ''),
            'total_cart_header' => session()->get('total_cart_header'),
            'link_cart' => route('checkout.cart')
        ]);
    }

    public function consultSign()
    {
        $params = $this->getParams();
        $options = array_get($params, 'option', []);
        list($product, $errors) = $this->_validateAddToCart($params, $options, 'sign');

        if (empty($product)) {
            return errValidator(trans('messages.ErrorNotFoundProduct'), 200);
        }

        if (count($errors)) {
            return errValidator($errors, 200);
        }

        $this->_sendConsultSignToAdmin($product, $params, $options);

        return successData('SendSuccess', [
            'success_2' => sprintf(trans('messages.TextConsultSignSuccess'), $product->getUrlClient(), isset($product->productDescription) ? $product->productDescription->name : ''),
        ]);
    }

    public function saveOrder()
    {
        $this->_error = '';
        $this->_processDataCart();

        if (filled(request()->get('coupon', ''))) {
            if (empty($this->getOptions('coupon'))) {
                return redirect(route('checkout.index'))->with('failed', trans('messages.ErrorCoupon'));
            }
            session()->put('coupon', request()->get('coupon'));
            return redirect(route('checkout.index'))->with('success', trans('messages.SuccessAddCoupon'));
        }

        if (filled(request()->get('voucher', ''))) {
            if (empty($this->getOptions('voucher'))) {
                return redirect(route('checkout.index'))->with('failed', trans('messages.ErrorVoucher'));
            }
            session()->put('voucher', request()->get('voucher'));
            return redirect(route('checkout.index'))->with('success', trans('messages.SuccessAddVoucher'));
        }

        $totalData = [];
        $countProduct = $subTotal = $total = 0;
        $productData = $this->_processProductOfCart($subTotal, $total, $countProduct);
        if (empty($this->_error)) {
            $validator = $this->getRepository()->getValidator();
            if (!$validator->validateCreate(request()->all())) {
                $messages = $validator->errorsBag()->getMessages();
                return redirect()->to(route('checkout.index'))->withErrors($messages)->withInput();
            }

            if (count($productData)) {
                $this->getTotalCheckout($totalData, $total);
                DB::beginTransaction();
                try {
                    $uniqid = strtoupper(uniqid());
                    $reward = 0;
                    $orderId = $this->_saveDataToOrder($total, $uniqid);
                    $this->setOptions(['order_id' => $orderId]);

                    $dataPayment = $this->_buildDataForPayment($total);

                    $this->_saveDataToOrdersHistory();

                    $this->_saveDataToVoucherHistory($totalData);

                    $this->_saveDataToCouponHistory();

                    $this->_saveDataToOrderOption($productData, $reward);

                    $this->_saveDataToUserReward($reward);

                    $this->_saveDataToOrdersTotal($totalData);

                    $dataCustomer = $this->_processDataBeforeSendMail($uniqid);
                    if (filled(request()->get('email', ''))) {
                        $this->_sendNotificationToCustomer($productData, $totalData, $dataCustomer);
                    }
                    if (filled(getConfigDb('config_email_notification'))) {
                        $this->_sendNotificationToAdmin($productData, $totalData, $dataCustomer);
                    }

                    $urlRedirect = $this->_getUrlRedirectPaymentOrder($dataPayment);
                    DB::commit();
                    $this->_cart->clear();
                    session()->put('lastOrderSuccess', $orderId);
                    if (filled($urlRedirect)) {
                        return redirect($urlRedirect);
                    }
                    return redirect(route('checkout.success'))->with('success', trans('messages.SuccessCreateOrder'));
                } catch (\Exception $e) {
                    logError($e);
                    DB::rollBack();
                    return redirect(route('checkout.index'))->with('failed', trans('messages.ErrorCreateOrder'))->withInput();
                }
            }
        }
        return redirect(route('checkout.index'));
    }

    public function saveRepayment()
    {
        $params = $this->getParams();
        $validator = $this->getRepository()->getValidator();
        if (!$validator->validateRepayment($params)) {
            $messages = $validator->errorsBag()->getMessages();
            return back()->withErrors($messages)->withInput();
        }
        $order = $this->getRepository()
            ->where('id', $params['order_id'])
            ->where('user_id', getUserLoginId())
            ->first();
        if ($order) {
            $paymentCode = $params['payment_code'];
            if ($paymentCode == 'cod') {
                $order->fill([
                    'payment_code' => $paymentCode,
                    'order_status_id' => getConfigDb('order_status_id'),
                ])->save();
                return redirect()->to(route('account.detailOrder', ['id' => $order->id]))
                    ->with('success', trans('messages.UpdateSuccess'));
            }

            $dataPayment = $this->_buildDataForRePayment([
                'id' => $order->id,
                'telephone' => $order->telephone,
                'email' => $order->email,
                'total' => (int)$order->total,
                'payment_code' => $paymentCode,
            ]);
            $order->fill([
                'app_trans_id' => $dataPayment['app_trans_id'],
            ])->save();
            $orderPayment = $this->_zaloPay->createOrder($dataPayment);
            $urlRedirect = '';
            if ($orderPayment["return_code"] === 1) {
                $urlRedirect = $orderPayment['order_url'];
            }
            if (filled($urlRedirect)) {
                return redirect($urlRedirect);
            }
        }
        return back()->with('failed', trans('messages.ErrorRepaymentOrder'))->withInput();
    }

    public function paymentCallBack()
    {
        try {
            $params = json_decode(file_get_contents('php://input'), true);
            $result = $this->_zaloPay->verifyCallback($params);
            if ($result['return_code'] === 1) {
                $data = json_decode($params['data'], true);
                $order = Orders::where('app_trans_id', $data['app_trans_id'])->first();
                if ($order) {
                    DB::beginTransaction();
                    try {
                        $order->fill([
                            'zp_trans_id' => $data['zp_trans_id'],
                            'channel' => $data['channel'],
                            'order_status_id' => getConfigDb('order_payment_success_status_id')
                        ])->save();
                        OrdersHistory::create([
                            'order_id' => $order->id,
                            'order_status_id' => getConfigDb('order_payment_success_status_id'),
                        ]);
                        DB::commit();
                    } catch (\Exception $e) {
                        logError($e->getMessage());
                        DB::rollBack();
                    }
                }
            }
        } catch (\Exception $e) {
            logError($e);
        }
    }

    public function shipping()
    {
        $totalData = [];
        $countProduct = $subTotal = $total = 0;
        $this->_processDataCart();
        $this->_processProductOfCart($subTotal, $total, $countProduct);
        $this->getTotalCheckout($totalData, $total);
        return successData('SearchSuccess', $totalData, 0);
    }

    public function success()
    {
        $params = $this->getParams();
        $appTransId = array_get($params, 'apptransid', '');
        if (filled($appTransId)) {
            $this->_processUrlPaymentRedirect($params, $appTransId);
            return redirect(route('checkout.success'));
        }

        $entity = $this->getRepository()->where('id', session()->get('lastOrderSuccess', 0))->select('id', 'invoice_no', 'full_name', 'email', 'order_status_id', 'telephone')->first();
        if (empty($entity)) {
            return redirect(route('home'));
        }

        $this->_error = '';

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.checkout_success'), 'href' => route('checkout.success'), 'separator' => true]);

        $this->_processMetaSeo('_buildForSeoBySetting', 'seo_title_checkout_success', 'seo_description_checkout_success');

        return $this->render('client.infunstudio.checkout.success', [
            'entity' => $entity,
        ]);
    }

    protected function _processDataCart()
    {
        $products = $this->_cart->getProducts();
        $coupon = $this->getCoupon($products, getCouponCode());
        $voucher = $this->getVoucher(getVoucherCode());
        $this->setOptions([
            'products' => $products,
            'coupon' => $coupon,
            'voucher' => $voucher,
        ]);
    }

    protected function _processProductOfCart(&$subTotal, &$total, &$countProduct)
    {
        $products = $this->getOptions('products');
        if (!$this->_cart->hasProducts()) {
            $this->_error = trans('messages.ErrorProduct');
            return [];
        }

        if (!$this->_cart->hasStock() && getConfigDb('config_stock_checkout')) {
            $this->_error = trans('messages.ErrorStock');
        }

        foreach ($products as $product) {
            $productTotal = 0;

            foreach ($products as $product2) {
                if ($product2['id'] == $product['id']) {
                    $productTotal += $product2['quantity'];
                }
            }

            if ($product['minimum'] > $productTotal) {
                $this->_error = sprintf(trans('messages.ErrorMinimum'), $product['name'], $product['minimum']);
                break;
            }
        }

        $productData = [];
        foreach ($products as $product) {
            $optionData = [];

            foreach ($product['option'] as $option) {
                $childs = $option['child'];
                $childData = [];
                if (count($childs)) {
                    foreach ($childs as $child) {
                        $childData[] = [
                            'id' => $child['id'],
                            'product_option_value_2_id' => $child['id'],
                            'option_value_2_id' => $child['option_value_2_id'],
                            'name' => $child['name'],
                            'type' => $child['type'],
                            'variation' => $child['variation'],
                            'subtract' => $child['subtract'],
                            'value' => $child['value'],
                            'price' => $child['price'],
                            'price_prefix' => $child['price_prefix'],
                            'points' => $child['points'],
                            'points_prefix' => $child['points_prefix'],
                            'weight' => $child['weight'],
                            'weight_prefix' => $child['weight_prefix'],
                            'quantity' => $child['subtract'] ? ($child['quantity'] - $product['quantity']) : $child['quantity'],
                        ];
                    }
                }
                $optionData[] = [
                    'product_option_value_id' => $option['product_option_value_id'],
                    'option_id' => $option['option_id'],
                    'product_option_id' => $option['product_option_id'],
                    'image' => $option['image'],
                    'name' => $option['name'],
                    'type' => $option['type'],
                    'variation' => $option['variation'],
                    'value' => $option['value'],
                    'required' => $option['required'],
                    'child' => $childData,
                ];
            }

            $subTotal += $product['total'];
            $total += $product['price'] * $product['quantity'];
            $countProduct += $product['quantity'];

            $productData[] = [
                'key' => $product['key'],
                'id' => $product['id'],
                'image' => resizeImage($product['image'], 60, 60, 'client'),
                'name' => $product['name'],
                'model' => $product['model'],
                'option' => $optionData,
                'quantity' => $product['quantity'],
                'reward' => $product['reward'],
                'points' => $product['points'],
                'stock' => $product['stock'],
                'price' => $product['price'],
                'total' => $product['price'] * $product['quantity'],
                'url' => $product['url'],
                'remove' => route('checkout.cart', ['remove' => $product['key']]),
            ];
        }

        return $productData;
    }

    protected function _validateAddToCart($params, &$options, $action = 'cart')
    {
        $productId = array_get($params, 'product_id', 0);
        $product = Product::where('id', $productId)
            ->with([
                'productDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                }
            ])
            ->when($action == 'cart', function ($q) {
                $q->where('is_add_cart', 1);
            })
            ->dateAvailable()
            ->first();
        $errors = [];
        if ($product) {
            $quantity = array_get($params, 'quantity', 1);
            if ($quantity <= 0) {
                $errors['quantity'] = trans('messages.ErrorQuantity');
            }
            foreach ($options as $key => $opt) {
                if ($opt['required'] == 1) {
                    if (array_get($opt, 'variation', 2) == 1) {
                        if (empty(array_filter(array_get($opt, 'children', [])))) {
                            $errors[$key]['child'] = sprintf(trans('messages.TextRequiredChoose'), '');
                        }
                        if (empty($opt['product_option_value_id'])) {
                            $errors[$key]['parent'] = sprintf(trans('messages.TextRequiredChoose'), $opt['name']);
                        }
                    }
                    if (array_get($opt, 'variation', 2) == 2) {
                        if ($opt['type'] == 'file' || $opt['type'] == 'datetime' || $opt['type'] == 'date' || $opt['type'] == 'time') {
                            if (empty($opt['value'])) {
                                $errors[$key]['parent'] = sprintf(trans('messages.TextRequiredChoose'), $opt['name']);
                            }
                        }
                        if ($opt['type'] == 'text' || $opt['type'] == 'textarea' || $opt['type'] == 'email' || $opt['type'] == 'phone') {
                            if (empty($opt['value'])) {
                                $errors[$key]['parent'] = sprintf(trans('messages.TextRequiredInput'), $opt['name']);
                            } else {
                                if ($opt['type'] == 'email') {
                                    $validator = Validator::make(
                                        ['email' => $opt['value']],
                                        ['email' => 'email']
                                    );
                                    if ($validator->fails()) {
                                        $errors[$key]['parent'] = $validator->errors()->first();
                                    }
                                }
                                if ($opt['type'] == 'phone') {
                                    $validator = Validator::make(
                                        ['phone' => $opt['value']],
                                        ['phone' => 'min_length:8|max_length:12'],
                                        [
                                            'phone.min_length' => trans('messages.ErrorPhone'),
                                            'phone.max_length' => trans('messages.ErrorPhone'),
                                        ]
                                    );
                                    if ($validator->fails()) {
                                        $errors[$key]['parent'] = $validator->errors()->first();
                                    }
                                }
                            }
                        }
                        if ($opt['type'] == 'image' || $opt['type'] == 'select' || $opt['type'] == 'radio' || $opt['type'] == 'checkbox') {
                            if (empty($opt['product_option_value_id'])) {
                                $errors[$key]['parent'] = sprintf(trans('messages.TextRequiredChoose'), $opt['name']);
                            }
                        }
                    }
                } else {
                    if (!isset($opt['product_option_value_id'])) {
                        unset($options[$key]);
                    }
                }
            }
        }
        return [$product, $errors];
    }

    protected function _sendNotificationToCustomer($productData, $totalData, $data)
    {
        dispatch(new OrderCreateSendEmailJob($productData, $totalData, $data));
    }

    protected function _sendNotificationToAdmin($productData, $totalData, $data)
    {
        dispatch(new OrderCreateSendEmailToAdminJob($productData, $totalData, $data));
    }

    protected function _sendConsultSignToCustomer($product, $data, $options)
    {
        dispatch(new ConsultSignEmailToCustomer($product, $data, $options));
    }

    protected function _sendConsultSignToAdmin($product, $data, $options)
    {
        dispatch(new ConsultSignEmailToAdmin($product, $data, $options));
    }

    protected function _processDataBeforeSendMail($uniqid)
    {
        $params = $this->getParams();

        $ordersStatus = OrdersStatus::where('id', getConfigDb('order_status_id'))
            ->where('language_code', app()->getLocale())
            ->first();
        $payment = Payment::where('code', array_get($params, 'payment_code'))
            ->leftJoin('payment_description', function ($q) {
                $q->on('payment_description.payment_id', '=', 'payment.id')
                    ->where('language_code', app()->getLocale());
            })
            ->first();

        $data = array_merge($this->getParams(), [
            'uniqid' => $uniqid,
            'order_status' => $ordersStatus ? $ordersStatus->name : '',
            'payment_name' => $payment ? $payment->name : 'Trả tiền khi nhận hàng',
        ]);
        return $data;
    }
}
