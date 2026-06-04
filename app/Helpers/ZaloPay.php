<?php

namespace App\Helpers;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ZaloPay
{
    private $_publicKey;
    private $_uid;

    public function __construct()
    {
        $this->_publicKey = file_get_contents(storage_path('lib/zaloPay/public_key.pem'));
        $this->_uid = $this->getTimestamp();
    }

    public function verifyCallback($params = [])
    {
        $data = $params["data"];
        $requestMac = $params["mac"];

        $mac = ZaloPayMacGenerator::compute($data, getCoreConfig('zalo_pay.key2'));

        $result['return_code'] = 1;
        $result['return_message'] = 'success';
        if ($mac != $requestMac) {
            $result['return_code'] = -1;
            $result['return_message'] = 'mac not equal';
        }
        return $result;
    }


    public function verifyRedirect($data = [])
    {
        $reqChecksum = $data["checksum"];
        $checksum = ZaloPayMacGenerator::redirect($data, getCoreConfig('zalo_pay.key2'));

        return $reqChecksum === $checksum;
    }

    public function genTransID()
    {
        return date("ymd") . "_" . getCoreConfig('zalo_pay.app_id') . "_" . (++$this->_uid);
    }

    public function buildOrderData($params = [])
    {
        $embedData = [];

        if (array_key_exists("embed_data", $params)) {
            $embedData = $params["embed_data"];
        }

        $order = [
            "app_id" => (int)getCoreConfig('zalo_pay.app_id'),
            "app_time" => $this->getTimeStamp(),
            "app_trans_id" => $this->genTransID(),
            "app_user" => array_get($params, 'app_user', '0925226173'),
            "item" => json_encode(array_get($params, 'item', [])),
            "embed_data" => json_encode($embedData, JSON_FORCE_OBJECT),
            "bank_code" => array_get($params, 'bank_code', ''),
            "description" => array_get($params, 'description', ''),
            "amount" => $params['amount'],
            "title" => array_get($params, 'title', ''),
            "phone" => array_get($params, 'phone', ''),
            "email" => array_get($params, 'email', ''),
            "callback_url" => array_get($params, 'callback_url', ''),
        ];

        return $order;
    }

    public function createOrder($order = [])
    {
        $order['mac'] = ZaloPayMacGenerator::createOrder($order);
        $response = $this->doRequest(getCoreConfig('zalo_pay.api.order'), json_encode($order));
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    public function newQuickPayOrderData($params = [])
    {
        $order = $this->newCreateOrderData($params);
        $order['userip'] = array_get($params, 'userip', '127.0.0.1');
        openssl_public_encrypt($params['paymentcodeRaw'], $encrypted, $this->_publicKey);
        $order['paymentcode'] = base64_encode($encrypted);
        $order['mac'] = ZaloPayMacGenerator::quickPay($order, $params['paymentcodeRaw']);
        return $order;
    }

    public function quickPay($order = [])
    {
        $result = $this->doRequest(getCoreConfig('zalo_pay.api.quick_pay'), json_encode($order));
        return $result;
    }

    public function getOrderStatus($appTransId = '')
    {
        $params = [
            "app_id" => getCoreConfig('zalo_pay.app_id'),
            "app_trans_id" => $appTransId
        ];
        $params["mac"] = ZaloPayMacGenerator::getOrderStatus($params);
        $response = $this->doRequest(getCoreConfig('zalo_pay.api.order_status'), json_encode($params));
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    public function buildRefundData($params = [])
    {
        $data = [
            "app_id" => getCoreConfig('zalo_pay.app_id'),
            "timestamp" => $this->getTimestamp(),
            "m_refund_id" => $this->genTransID(),
            "zp_trans_id" => $params['zp_trans_id'],
            "amount" => (int)$params['amount'],
            "description" => $params['description']
        ];

        $data['mac'] = ZaloPayMacGenerator::refund($data);
        return $data;
    }

    public function refund($refundData = [])
    {
        $response = $this->doRequest(getCoreConfig('zalo_pay.api.refund'), json_encode($refundData));

        if ($response) {
            $response = json_decode($response->getBody()->getContents(), true);
            return $response;
        }

        return [];
    }

    public function getRefundStatus($mRefundId = '')
    {
        $params = [
            "app_id" => getCoreConfig('zalo_pay.app_id'),
            "m_refund_id" => (string)$mRefundId,
            "timestamp" => $this->getTimestamp()
        ];

        $params['mac'] = ZaloPayMacGenerator::getRefundStatus($params);
        $response = $this->doRequest(getCoreConfig('zalo_pay.api.refund_status'), json_encode($params));
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    public function getBankList()
    {
        $params = [
            "appid" => getCoreConfig('zalo_pay.app_id'),
            "reqtime" => $this->getTimestamp()
        ];

        $params['mac'] = ZaloPayMacGenerator::getBankList($params);
        return $this->doRequest(getCoreConfig('zalo_pay.api.bank_list'), json_encode($params));
    }

    public function getTimestamp()
    {
        return round(microtime(true) * 1000);
    }

    public function doRequest($url, $params = [], $method = 'post', $header = [])
    {
        if (empty($header)) {
            $header = [
                'Content-type' => 'application/json'
            ];
        }
        $client = new Client(['headers' => $header]);
        try {
            if (strtolower($method) == 'get') {
                return $client->request($method, addParamToUrl($url, $params));
            }
            return $client->request($method, $url, [
                'body' => $params
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                logError($e->getResponse()->getBody());
            }
        } catch (\Exception $e) {
            logError($e->getMessage());
        }
        return false;
    }
}
