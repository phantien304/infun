<?php

namespace App\Services\Payment;

use App\Helpers\ZaloPayMacGenerator;
use Illuminate\Support\Facades\Http;

/**
 * Cổng thanh toán ZaloPay (Open API v2).
 *
 * Bọc toàn bộ giao tiếp với ZaloPay: dựng payload, ký MAC (uỷ quyền cho
 * ZaloPayMacGenerator), gọi HTTP và xác minh callback/redirect.
 *
 * Được inject vào CheckoutPaymentService / RefundService. KHÔNG đọc public key
 * ở constructor — service này bị instantiate ở MỌI request checkout (kể cả
 * addToCart không dùng ZaloPay); nếu thiếu file key sẽ 500 cả luồng. Public key
 * được lazy-load qua getPublicKey(), chỉ đọc khi quick-pay thực sự cần encrypt.
 */
class ZaloPayService
{
    private ?string $publicKey = null;

    /** Bộ đếm tăng dần để sinh app_trans_id / m_refund_id trong cùng request. */
    private int $transSequence;

    public function __construct()
    {
        $this->transSequence = $this->timestamp();
    }

    /* ------------------------------------------------------------------ *
     |  Xác minh callback & redirect
     * ------------------------------------------------------------------ */

    public function verifyCallback(array $params = []): array
    {
        $data       = (string) ($params['data'] ?? '');
        $requestMac = (string) ($params['mac'] ?? '');

        $mac = ZaloPayMacGenerator::compute($data, $this->cfg('key2'));

        if (! hash_equals($mac, $requestMac)) {
            return ['return_code' => -1, 'return_message' => 'mac not equal'];
        }

        return ['return_code' => 1, 'return_message' => 'success'];
    }

    public function verifyRedirect(array $data = []): bool
    {
        $requestChecksum = (string) ($data['checksum'] ?? '');
        $checksum        = ZaloPayMacGenerator::redirect($data, $this->cfg('key2'));

        return hash_equals($checksum, $requestChecksum);
    }

    /* ------------------------------------------------------------------ *
     |  Tạo đơn & tra cứu trạng thái
     * ------------------------------------------------------------------ */

    public function buildOrderData(array $params = []): array
    {
        return [
            'app_id'       => (int) $this->cfg('app_id'),
            'app_time'     => $this->timestamp(),
            'app_trans_id' => $this->generateTransId(),
            'app_user'     => $params['app_user'] ?? '0925226173',
            'item'         => json_encode($params['item'] ?? []),
            'embed_data'   => json_encode($params['embed_data'] ?? [], JSON_FORCE_OBJECT),
            'bank_code'    => $params['bank_code'] ?? '',
            'description'  => $params['description'] ?? '',
            'amount'       => $params['amount'],
            'title'        => $params['title'] ?? '',
            'phone'        => $params['phone'] ?? '',
            'email'        => $params['email'] ?? '',
            'callback_url' => $params['callback_url'] ?? '',
        ];
    }

    public function createOrder(array $order = []): array
    {
        $order['mac'] = ZaloPayMacGenerator::createOrder($order);

        return $this->request($this->cfg('api.order'), $order);
    }

    public function getOrderStatus(string $appTransId = ''): array
    {
        $params = [
            'app_id'       => $this->cfg('app_id'),
            'app_trans_id' => $appTransId,
        ];
        $params['mac'] = ZaloPayMacGenerator::getOrderStatus($params);

        return $this->request($this->cfg('api.order_status'), $params);
    }

    /* ------------------------------------------------------------------ *
     |  Quick pay (giữ lại — chưa dùng ở luồng checkout hiện tại)
     * ------------------------------------------------------------------ */

    public function newQuickPayOrderData(array $params = []): array
    {
        $order           = $this->buildOrderData($params);
        $order['userip'] = $params['userip'] ?? '127.0.0.1';

        openssl_public_encrypt($params['paymentcodeRaw'], $encrypted, $this->getPublicKey());
        $order['paymentcode'] = base64_encode($encrypted);
        $order['mac']         = ZaloPayMacGenerator::quickPay($order, $params['paymentcodeRaw']);

        return $order;
    }

    public function quickPay(array $order = []): array
    {
        return $this->request($this->cfg('api.quick_pay'), $order);
    }

    /* ------------------------------------------------------------------ *
     |  Hoàn tiền
     * ------------------------------------------------------------------ */

    public function buildRefundData(array $params = []): array
    {
        $data = [
            'app_id'      => $this->cfg('app_id'),
            'timestamp'   => $this->timestamp(),
            'm_refund_id' => $this->generateTransId(),
            'zp_trans_id' => $params['zp_trans_id'],
            'amount'      => (int) $params['amount'],
            'description' => $params['description'],
        ];
        $data['mac'] = ZaloPayMacGenerator::refund($data);

        return $data;
    }

    public function refund(array $refundData = []): array
    {
        return $this->request($this->cfg('api.refund'), $refundData);
    }

    public function getRefundStatus(string $mRefundId = ''): array
    {
        $params = [
            'app_id'      => $this->cfg('app_id'),
            'm_refund_id' => (string) $mRefundId,
            'timestamp'   => $this->timestamp(),
        ];
        $params['mac'] = ZaloPayMacGenerator::getRefundStatus($params);

        return $this->request($this->cfg('api.refund_status'), $params);
    }

    /* ------------------------------------------------------------------ *
     |  Danh sách ngân hàng (giữ lại — chưa dùng)
     * ------------------------------------------------------------------ */

    public function getBankList(): array
    {
        $params = [
            'appid'   => $this->cfg('app_id'),
            'reqtime' => $this->timestamp(),
        ];
        $params['mac'] = ZaloPayMacGenerator::getBankList($params);

        return $this->request($this->cfg('api.bank_list'), $params);
    }

    /* ------------------------------------------------------------------ *
     |  Nội bộ
     * ------------------------------------------------------------------ */

    /** Gọi ZaloPay: gửi JSON body, trả mảng đã decode; [] nếu lỗi/timeout. */
    private function request(string $url, array $payload = [], string $method = 'post'): array
    {
        try {
            $response = strtolower($method) === 'get'
                ? Http::acceptJson()->get($url, $payload)
                : Http::withBody(json_encode($payload), 'application/json')->post($url);

            if ($response->failed()) {
                logError($response->body());

                return [];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            logError($e->getMessage());

            return [];
        }
    }

    /** app_trans_id / m_refund_id: yyMMdd_{app_id}_{seq}. */
    private function generateTransId(): string
    {
        return date('ymd') . '_' . $this->cfg('app_id') . '_' . (++$this->transSequence);
    }

    /** Epoch tính bằng mili-giây (ZaloPay yêu cầu ms). */
    private function timestamp(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    private function getPublicKey(): string
    {
        if ($this->publicKey !== null) {
            return $this->publicKey;
        }

        $path = storage_path('lib/zaloPay/public_key.pem');
        if (! is_file($path)) {
            throw new \RuntimeException(
                "ZaloPay public key not found at {$path}. "
                . 'Tải file từ ZaloPay merchant portal và đặt vào storage/lib/zaloPay/public_key.pem.'
            );
        }

        return $this->publicKey = (string) file_get_contents($path);
    }

    /** Đọc config zalo_pay.* qua một cửa duy nhất. */
    private function cfg(string $key): mixed
    {
        return getCoreConfig('zalo_pay.' . $key);
    }
}
