<?php

namespace App\Services\Checkout;

use App\Models\Entities\District;
use App\Models\Entities\Ward;
use App\Models\Entities\Zone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Tính phí ship qua các provider: flat, GHN, GHTK, VTP.
 *
 * Đã hợp nhất với `App\Services\FeeShipService` cũ — không còn delegate qua
 * BaseService. Guzzle HTTP call inline vào service này; mỗi provider có
 * method `fetchXxx` riêng để dễ mock/test.
 *
 * Signature ngoài: `calculate($method, $orderTotal, $cartShipping, $address)`
 * trả `[bool $ok, ?int $fee]`. `CheckoutTotalService` chỉ gắn dòng phí khi
 * `$ok = true` — provider lỗi (token sai, district không hợp lệ, network
 * timeout) trả `[false, null]` để UI bỏ qua dòng phí thay vì hiển thị 0đ
 * gây nhầm "free ship".
 *
 * Toàn bộ config (token, url, from address) đọc qua `setting()` để CMS
 * override được — convention mới thay `getCoreConfig()` cho phần config DB
 * không cố định.
 */
class ShippingFeeService
{
    protected Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 5]);
    }

    public function calculate(string $method, int $orderTotal, array $cartShipping, array $address): array
    {
        return match ($method) {
            'flat' => $this->flat($orderTotal),
            'ghn'  => $this->ghn($cartShipping, $address),
            'ghtk' => $this->ghtk($orderTotal, $cartShipping, $address),
            'vtp'  => $this->vtp($cartShipping, $address),
            default => [false, null],
        };
    }

    protected function flat(int $orderTotal): array
    {
        $fee = (int) getConfigDb('config_fee_ship');
        $freeFrom = (int) getConfigDb('config_total_free_ship');
        if ($freeFrom > 0 && $orderTotal >= $freeFrom) {
            $fee = 0;
        }

        return [true, $fee];
    }

    protected function ghn(array $cartShipping, array $address): array
    {
        [$toDistrictId, $toWardCode] = $this->ghnIds(
            (int) ($address['district_id'] ?? 0),
            (int) ($address['ward_id'] ?? 0)
        );
        if (! $toDistrictId || ! $toWardCode) {
            return [false, null];
        }

        $params = [
            'from_district_id' => setting('ghn.from_district_id'),
            'to_district_id'   => $toDistrictId,
            'to_ward_code'     => $toWardCode,
            'height'           => (int) ($cartShipping['height'] ?? 0),
            'length'           => (int) ($cartShipping['length'] ?? 0),
            'width'            => (int) ($cartShipping['width'] ?? 0),
            'weight'           => (int) ($cartShipping['weight'] ?? 0),
            'service_type_id'  => setting('ghn.service_type_id.walk'),
        ];

        $data = $this->fetchGet(setting('ghn.url_fee'), $params, [
            'token'  => setting('ghn.token'),
            'ShopId' => setting('ghn.shop_id'),
        ]);
        $total = data_get($data, 'data.total');

        return $total !== null ? [true, (int) $total] : [false, null];
    }

    protected function ghtk(int $orderTotal, array $cartShipping, array $address): array
    {
        $params = [
            'pick_province'  => setting('ghtk.from_province_name'),
            'pick_district'  => setting('ghtk.from_district_name'),
            'province'       => $address['zone_name'] ?? '',
            'district'       => $address['district_name'] ?? '',
            'address'        => implode(', ', array_filter([$address['address'] ?? '', $address['ward_name'] ?? ''])),
            'weight'         => (int) ($cartShipping['weight'] ?? 0),
            'value'          => $orderTotal,
            'deliver_option' => setting('ghtk.deliver_option')[1] ?? null,
        ];

        $data = $this->fetchGet(setting('ghtk.url_fee'), $params, [
            'Token' => setting('ghtk.token'),
        ]);
        if (empty($data['success'])) {
            return [false, null];
        }

        return [true, (int) data_get($data, 'fee.fee', 0)];
    }

    protected function vtp(array $cartShipping, array $address): array
    {
        [$toZoneId, $toDistrictId] = $this->vtpIds(
            (int) ($address['zone_id'] ?? 0),
            (int) ($address['district_id'] ?? 0)
        );
        if (! $toZoneId || ! $toDistrictId) {
            return [false, null];
        }

        $params = [
            'PRODUCT_WEIGHT'    => (int) ($cartShipping['weight'] ?? 0),
            'PRODUCT_PRICE'     => 0,
            'MONEY_COLLECTION'  => 0,
            'ORDER_SERVICE_ADD' => '',
            'ORDER_SERVICE'     => 'VCN',
            'SENDER_PROVINCE'   => setting('vtp.from_zone_id'),
            'SENDER_DISTRICT'   => setting('vtp.from_district_id'),
            'RECEIVER_PROVINCE' => $toZoneId,
            'RECEIVER_DISTRICT' => $toDistrictId,
            'PRODUCT_TYPE'      => 'HH',
            'NATIONAL_TYPE'     => 1,
            'PRODUCT_HEIGHT'    => (int) ($cartShipping['height'] ?? 0),
            'PRODUCT_LENGTH'    => (int) ($cartShipping['length'] ?? 0),
            'PRODUCT_WIDTH'     => (int) ($cartShipping['width'] ?? 0),
        ];

        $data = $this->fetchPostJson(setting('vtp.url_fee'), $params, [
            'token'        => setting('vtp.token'),
            'Content-Type' => 'application/json',
        ]);
        if (empty($data) || ! empty($data['error'])) {
            return [false, null];
        }
        $total = data_get($data, 'data.MONEY_TOTAL');

        return $total !== null ? [true, (int) $total] : [false, null];
    }

    public function ghnIds(?int $districtId, ?int $wardId): array
    {
        $district = $districtId ? District::find($districtId) : null;
        $ward = $wardId ? Ward::find($wardId) : null;

        return [$district?->ghn_id, $ward?->ghn_id];
    }

    public function vtpIds(?int $zoneId, ?int $districtId): array
    {
        $zone = $zoneId ? Zone::find($zoneId) : null;
        $district = $districtId ? District::find($districtId) : null;

        return [$zone?->vtp_id, $district?->vtp_id];
    }

    /**
     * Guzzle GET với query string + custom headers, parse JSON. Trả `[]` khi
     * lỗi network / status != 2xx — caller check `empty()` để fallback.
     */
    protected function fetchGet(?string $url, array $query, array $headers): array
    {
        if (! filled($url)) {
            return [];
        }
        try {
            $response = $this->http->get($url, [
                'query'   => $query,
                'headers' => $headers,
            ]);

            return json_decode((string) $response->getBody(), true) ?: [];
        } catch (GuzzleException $e) {
            logError($e->getMessage());

            return [];
        }
    }

    /**
     * Guzzle POST JSON body. Cùng convention trả `[]` khi lỗi.
     */
    protected function fetchPostJson(?string $url, array $body, array $headers): array
    {
        if (! filled($url)) {
            return [];
        }
        try {
            $response = $this->http->post($url, [
                'json'    => $body,
                'headers' => $headers,
            ]);

            return json_decode((string) $response->getBody(), true) ?: [];
        } catch (GuzzleException $e) {
            logError($e->getMessage());

            return [];
        }
    }
}
