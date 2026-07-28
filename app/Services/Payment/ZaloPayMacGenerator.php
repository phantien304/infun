<?php

namespace App\Services\Payment;

class ZaloPayMacGenerator
{
    static function compute($params, $key = null)
    {
        if (is_null($key)) {
            $key = getCoreConfig('zalo_pay.key1');
        }
        return hash_hmac("sha256", $params, $key);
    }

    private static function createOrderMacData($order = [])
    {
        return $order["app_id"] . "|" . $order["app_trans_id"] . "|" . $order["app_user"] . "|" . $order["amount"]
            . "|" . $order["app_time"] . "|" . $order["embed_data"] . "|" . $order["item"];
    }

    static function createOrder($order = [])
    {
        return self::compute(self::createOrderMacData($order));
    }

    static function quickPay($order, $paymentCodeRaw)
    {
        return self::compute(self::createOrderMacData($order) . "|" . $paymentCodeRaw);
    }

    static function refund($params = [])
    {
        return self::compute($params['app_id'] . "|" . $params['zp_trans_id'] . "|" . $params['amount'] . "|" . $params['description'] . "|" . $params['timestamp']);
    }

    static function getOrderStatus($params = [])
    {
        return self::compute($params['app_id'] . "|" . $params['app_trans_id'] . "|" . getCoreConfig('zalo_pay.key1'));
    }

    static function getRefundStatus($params = [])
    {
        return self::compute($params['app_id'] . "|" . $params['m_refund_id'] . "|" . $params['timestamp']);
    }

    static function getBankList($params = [])
    {
        return self::compute($params['app_id'] . "|" . $params['reqtime']);
    }

    static function redirect($params = [], $key2 = '')
    {
        return self::compute($params['appid'] . "|" . $params['apptransid'] . "|" . $params['pmcid'] . "|" . $params['bankcode']
            . "|" . $params['amount'] . "|" . $params['discountamount'] . "|" . $params["status"], $key2);
    }
}
