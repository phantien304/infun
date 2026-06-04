<?php

return [
    'area_mapping' => [
        'cms' => 'cms',
        'web' => 'web',
        'api' => 'api',
    ],
    'user' => [
        'type' => [
            'admin' => 1,
            'member' => 2,
        ]
    ],
    'extension' => [
        'type' => [
            'module' => 'module'
        ],
        'list' => [
            'module' => [
                'feature' => 'Sản phẩm nổi bật',
                'bestsell' => 'Sản phẩm bán chạy',
            ]
        ]
    ],
    'url' => [
        'forgot_password' => 'change-password?token=',
        'verify_email' => 'verify-email?token=',
        'product' => 'p',
        'category' => 'c',
        'manufacturer' => 'm',
        'information' => 'i',
        'blog_category' => 'bc',
        'blog' => 'n',
        'store_review' => 'sr',
        'tag' => 't'
    ],
    'variation' => [
        1 => 'Phân loại hàng 1',
        2 => 'Phân loại hàng 2'
    ],
    'ghn' => [
        'url_fee' => 'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee',
        'url_province' => 'https://dev-online-gateway.ghn.vn/shiip/public-api/master-data/province',
        'url_district' => 'https://dev-online-gateway.ghn.vn/shiip/public-api/master-data/district',
        'url_ward' => 'https://dev-online-gateway.ghn.vn/shiip/public-api/master-data/ward',
        'url_order_detail' => 'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail',
        'order_create' => 'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create',
        'url_public_order_detail' => 'https://fe-online-gateway.ghn.vn/order-tracking/public-api/client/tracking-logs',
        'token' => 'f8df7cd5-2263-11eb-9f0c-5af6fb9de075',
        'shop_id' => 75946,
        //        'url_ward' => 'https://online-gateway.ghn.vn/shiip/public-api/master-data/ward',
        //        'token' => '537028d3-281d-11eb-b36a-0e2790f48b9c',
        //        'shop_id' => 431818,
        'from_district_id' => 1490,
        'service_type_id' => [
            'fly' => 1,
            'walk' => 2
        ],
    ],
    'ghtk' => [
        'url_fee' => 'https://services.giaohangtietkiem.vn/services/shipment/fee?',
        'token' => '4f0444d3BFeD0e45EE2bA3C4edfDA05ccBb515f3',
        'from_province_name' => 'Hà Nội',
        'from_district_name' => 'Quận Hoàng Mai',
        'deliver_option' => ['xteam', 'none'],
    ],
    'vtp' => [
        'url_province' => 'https://partner.viettelpost.vn/v2/categories/listProvinceById',
        'url_district' => 'https://partner.viettelpost.vn/v2/categories/listDistrict',
        'url_ward' => 'https://partner.viettelpost.vn/v2/categories/listWards',
        'url_fee' => 'https://partner.viettelpost.vn/v2/order/getPrice',
        'token' => 'eyJhbGciOiJFUzI1NiJ9.eyJVc2VySWQiOjEwMDQ5MDI4LCJGcm9tU291cmNlIjo1LCJUb2tlbiI6IlczN01TUk00WDdWQTNJVlU4SiIsImV4cCI6MTcxNzM5NjUyMiwiUGFydG5lciI6MTAwNDkwMjh9.c9INXA4CZrNYVQBlhQb4QKEN4CcRpu0H3lZDEY1dBNBVRIb7-JI3vCySrRZDvoSci2aha9dgmYhoOCa8plBQ8A',
        'from_zone_id' => 1,
        'from_district_id' => 4,
    ],
    'reward_point' => [
        'enable' => 1,
        'disable' => 0
    ],
    'cookie' => [
        'time' => 1051200,
        'user' => [
            'address' => 'address_customer'
        ],
        'zone' => 'zone_resource',
        'shipping_zone' => 'shipping_zone'
    ],
    'session' => [
        'total_wishlist' => 'user_total_wishlist'
    ],
    'shipping' => [
        'default' => 'vtp'
    ],
    'payment' => [
        'default' => 'cod'
    ],
    'zalo_pay' => [
        "app_id" => 2554,
        "key1" => "sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn",
        "key2" => "trMrHtvjo6myautxDUiAcYsVtaeQ8nhf",
        "api" => [
            "order" => "https://sb-openapi.zalopay.vn/v2/create",
            "gateway" => "https://sbgateway.zalopay.vn/pay?order=",
            "quick_pay" => "https://sb-openapi.zalopay.vn/v2/quick_pay",
            "refund" => "https://sb-openapi.zalopay.vn/v2/refund",
            "refund_status" => "https://sb-openapi.zalopay.vn/v2/query_refund",
            "order_status" => "https://sb-openapi.zalopay.vn/v2/query",
            "bank_list" => "https://sbgateway.zalopay.vn/api/getlistmerchantbanks"
        ],
    ],
    'cache' => [
        'menu' => 'menu_',
        'categories' => 'categories_',
        'zones' => 'zones_',
        'setting' => 'setting',
        'language' => 'language',
        'manufacturers' => 'manufacturers',
        'store_reviews' => 'store_reviews_',
        'filters' => 'filters_',
        'products' => 'product_',
        'product_latest' => 'product_latest',
        'product_special_latest' => 'product_special_latest',
        'product_root' => 'product',
        'blog' => 'blog_',
        'blog_categories' => 'blog_categories_',
        'blog_tags' => 'blog_tags_',
        'information' => 'information_',
        'carriers' => 'carriers_',
        'payments' => 'payments_',
    ],
    'zones' => [
        'country_id_default' => 230
    ],
    'view' => [
        'unread' => 0,
        'read' => 1,
    ],
    'time' => [
        'cache' => 3600
    ],
    'folder_cache' => 'cache',
    'product_image' => [
        'type' => [
            'main' => 'main',
            'gallery' => 'gallery',
            'thumbnail' => 'thumbnail',
            'zoom' => 'zoom',
            '360' => '360',
        ]
    ],
    'option' => [
        'role_custom_field' => 0,
        'role_variant' => 1,
    ]
];
