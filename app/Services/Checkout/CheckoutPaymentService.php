<?php

namespace App\Services\Checkout;

use App\Helpers\ZaloPay;
use App\Repositories\Interfaces\OrderRepositoryInterface;

/**
 * Wrapper quanh ZaloPay helper — port từ trait CheckoutPayment cũ. Service
 * tách khỏi controller để controller chỉ orchestrate, không biết chi tiết
 * payload ZaloPay.
 *
 * Stateless. Mọi state (order_id, total, telephone) truyền tường minh qua
 * tham số method.
 */
class CheckoutPaymentService
{
    public function __construct(
        protected ZaloPay $zaloPay,
        protected OrderRepositoryInterface $orderRepo,
    ) {
    }

    /**
     * Build payload cho ZaloPay::createOrder cho luồng checkout mới. Trả [] khi
     * paymentCode là 'cod' (không cần thanh toán cổng).
     */
    public function buildOrderPayload(string $paymentCode, int $orderId, int $total, ?string $phone, ?string $email): array
    {
        if ($paymentCode === 'cod' || $paymentCode === '') {
            return [];
        }

        $data = [
            'embed_data'   => [],
            'description'  => sprintf(trans('messages.zalo_pay.create_order.description'), $orderId, getConfigDb('config_name')),
            'amount'       => $total,
            'phone'        => $phone,
            'email'        => $email,
            'callback_url' => route('checkout.paymentCallBack'),
        ];
        if ($paymentCode === 'atm_zalo') {
            $data['embed_data'] = ['bankgroup' => 'ATM', 'redirecturl' => route('checkout.success')];
        } elseif ($paymentCode === 'visa_zalo') {
            $data['embed_data'] = ['bankgroup' => 'CC', 'redirecturl' => route('checkout.success')];
        }

        return $this->zaloPay->buildOrderData($data);
    }

    /**
     * Build payload cho luồng REPAYMENT (account.detailOrder redirect). Giống
     * buildOrderPayload nhưng redirect về trang chi tiết order thay vì success.
     */
    public function buildRepaymentPayload(array $orderData): array
    {
        $code = $orderData['payment_code'] ?? '';
        $data = [
            'description'  => sprintf(trans('messages.zalo_pay.create_order.description'), $orderData['id'], getConfigDb('config_name')),
            'amount'       => (int) $orderData['total'],
            'phone'        => $orderData['telephone'] ?? null,
            'email'        => $orderData['email'] ?? null,
            'callback_url' => route('checkout.paymentCallBack'),
        ];
        if ($code === 'atm_zalo' || $code === 'visa_zalo') {
            $group = $code === 'atm_zalo' ? 'ATM' : 'CC';
            $data['embed_data'] = [
                'bankgroup'   => $group,
                'redirecturl' => route('account.detailOrder', ['id' => $orderData['id']]),
            ];
        }

        return $this->zaloPay->buildOrderData($data);
    }

    /**
     * Gọi ZaloPay createOrder, update order với app_trans_id + status WAITING,
     * trả URL redirect hoặc '' nếu thất bại.
     */
    public function startPayment(int $orderId, array $payload): string
    {
        if (empty($payload)) {
            return '';
        }
        $this->orderRepo->upsertOrder([
            'id'              => $orderId,
            'app_trans_id'    => $payload['app_trans_id'],
            'order_status_id' => getConfigDb('order_payment_waiting_status_id'),
        ]);

        $response = $this->zaloPay->createOrder($payload);

        return (int) ($response['return_code'] ?? 0) === 1 ? (string) ($response['order_url'] ?? '') : '';
    }

    /**
     * Xử lý redirect callback từ ZaloPay (user sau khi thanh toán). Verify
     * checksum + cập nhật order status theo getOrderStatus.
     */
    public function processRedirect(array $params, string $appTransId): void
    {
        if (! $this->zaloPay->verifyRedirect($params)) {
            return;
        }

        $order = $this->orderRepo->findByAppTransId($appTransId);
        if (! $order || (int) $order->order_status_id !== (int) getConfigDb('order_payment_waiting_status_id')) {
            return;
        }

        $status = $this->zaloPay->getOrderStatus($appTransId);
        $code = (int) ($status['return_code'] ?? 0);

        if ($code === 1) {
            $statusId = (int) getConfigDb('order_payment_success_status_id');
            $this->orderRepo->upsertOrder([
                'id'              => $order->id,
                'order_status_id' => $statusId,
                'zp_trans_id'     => $status['zp_trans_id'] ?? null,
            ]);
            $this->orderRepo->ensureHistory($order->id, $statusId);
        } elseif ($code === 2) {
            $statusId = (int) getConfigDb('order_payment_failed_status_id');
            $this->orderRepo->upsertOrder([
                'id'              => $order->id,
                'order_status_id' => $statusId,
            ]);
            $this->orderRepo->ensureHistory($order->id, $statusId);
        }
    }

    /**
     * Xử lý IPN callback từ ZaloPay (server-to-server). KHÔNG trả response
     * cho ZaloPay ở đây — caller (controller) chịu trách nhiệm echo JSON.
     */
    public function processCallback(string $rawBody): void
    {
        $params = json_decode($rawBody, true) ?: [];
        $result = $this->zaloPay->verifyCallback($params);
        if ((int) ($result['return_code'] ?? 0) !== 1) {
            return;
        }

        $data = json_decode($params['data'] ?? '[]', true) ?: [];
        $order = $this->orderRepo->findByAppTransId($data['app_trans_id'] ?? '');
        if (! $order) {
            return;
        }

        $statusId = (int) getConfigDb('order_payment_success_status_id');
        $this->orderRepo->upsertOrder([
            'id'              => $order->id,
            'zp_trans_id'     => $data['zp_trans_id'] ?? null,
            'channel'         => $data['channel'] ?? null,
            'order_status_id' => $statusId,
        ]);
        $this->orderRepo->appendHistory($order->id, $statusId);
    }
}
