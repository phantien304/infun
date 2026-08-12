<?php

return [
   'ghn' => [
       'url_fee' => 'https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee',
       'url_province' => 'https://online-gateway.ghn.vn/shiip/public-api/master-data/province',
       'url_district' => 'https://online-gateway.ghn.vn/shiip/public-api/master-data/district',
       'url_ward' => 'https://online-gateway.ghn.vn/shiip/public-api/master-data/ward',
       'url_order_detail' => 'https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail',
       'order_create' => 'https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create',
       'url_public_order_detail' => 'https://fe-online-gateway.ghn.vn/order-tracking/public-api/client/tracking-logs',
       'token' => '537028d3-281d-11eb-b36a-0e2790f48b9c',
       'shop_id' => 431818,
       'from_district_id' => 1488,
       'from_ward_code' => '1A0320',
       'service_type_id' => [
            'fly' => 1,
            'walk' => 2
        ],
   ],
    'zalo_pay' => [
        "appid" => 2554,
        "key1" => "sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn",
        "key2" => "trMrHtvjo6myautxDUiAcYsVtaeQ8nhf",
//        "api" => [
//            "order" => "https://sb-openapi.zalopay.vn/v2/create",
//            "gateway" => "https://sbgateway.zalopay.vn/pay?order=",
//            "quick_pay" => "https://openapi.zalopay.vn/v2/quick_pay",
//            "refund" => "https://openapi.zalopay.vn/v2/refund",
//            "refund_status" => "https://sb-openapi.zalopay.vn/v2/query_refund",
//            "order_status" => "https://openapi.zalopay.vn/v2/query",
//            "bank_list" => "https://gateway.zalopay.vn/api/getlistmerchantbanks"
//        ],
        "api" => [
            "order" => "https://sb-openapi.zalopay.vn/v2/create",
            "gateway" => "https://sbgateway.zalopay.vn/pay?order=",
            "quick_pay" => "https://sb-openapi.zalopay.vn/v2/quick_pay",
            "refund" => "https://sb-openapi.zalopay.vn/v2/refund",
            "refund_status" => "https://sb-openapi.zalopay.vn/v2/query_refund",
            "order_status" => "https://sb-openapi.zalopay.vn/v2/query",
            "bank_list" => "https://sbgateway.zalopay.vn/api/getlistmerchantbanks"
        ],
    ]
];
