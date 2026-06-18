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
        'total_wishlist'   => 'user_total_wishlist',
        // Cart / checkout session keys — GIỮ NGUYÊN value (gồm dot-notation
        // 'checkout.*' = mảng lồng). Đổi value = orphan session khách đang
        // checkout + có thể tái phát bug prefix (xem CLAUDE.md).
        'cart'             => 'cart',                    // prefix → cart.{key}
        'cart_header'      => 'total_cart_header',
        'cart_shipping'    => 'cart_shipping',
        'reward'           => 'reward',
        'last_order'       => 'lastOrderSuccess',
        'applied_coupons'  => 'checkout.applied_coupons',
        'applied_gifts'    => 'checkout.applied_gifts',
        'applied_vouchers' => 'checkout.applied_vouchers',
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
        'store_reviews_featured' => 'store_reviews_featured_',
        'store_reviews_product' => 'store_reviews_product_',
        'filters' => 'filters_',
        'products' => 'product_',
        'product_latest' => 'product_latest',
        'product_special_latest' => 'product_special_latest',
        'product_related' => 'product_related',
        'product_root' => 'product',
        'blog' => 'blog_',
        'blog_categories' => 'blog_categories_',
        'blog_tags' => 'blog_tags_',
        'information' => 'information_',
        'carriers' => 'carriers_',
        'payments' => 'payments_',
        'review' => [
            'tag_root'         => 'review_root',
            'tag_criteria'     => 'review_criteria',
            'tag_tag'          => 'review_tag',
            'tag_product'      => 'reviews:',
            'key_criteria_active' => 'review_criteria_active',
            'key_tag_top'         => 'review_tag_top',
            'key_criteria_avg'    => 'review_criteria_avg_',
        ],
    ],
    'zones' => [
        'country_id_default' => 230
    ],
    'coupon' => [
        // type values khớp DB-level `coupon.type TINYINT UNSIGNED`.
        // KHÔNG dùng literal 1/2/3 trong code — đọc qua getCoreConfig.
        'type' => [
            'percent'  => 1, // discount_value = %, kèm discount_max cap VND.
            'fixed'    => 2, // discount_value = VND tuyệt đối.
            'freeship' => 3, // bỏ qua discount_value, set shipping_fee=0.
        ],
        'apply_scope' => [
            'all'        => 0, // áp mọi SP trong cart.
            'products'   => 1, // chỉ SP có row trong coupon_product.
            'categories' => 2, // chỉ SP thuộc category có row trong coupon_category.
        ],
        'history_status' => [
            'applied'   => 0, // đang trong cart, chưa thanh toán.
            'used'      => 1, // order paid → trừ used_count.
            'cancelled' => 2, // order cancel → trả quota lại.
        ],
        'stacking' => [
            // 1 voucher discount (percent | fixed) + 1 voucher freeship/order.
            // Set false để cấm stack hoàn toàn (chỉ 1 voucher).
            'allow_freeship_with_discount' => true,
        ],
        'cache' => [
            'tag_root'   => 'coupon_root',
            'tag_user'   => 'coupon_user_', // concat user_id → tag riêng (saved list).
            'key_active' => 'coupon_active',
        ],
        // TTL áp riêng cho cart pending — quá hạn auto chuyển status applied → expired
        // (cron job) để giải phóng quota cho user khác.
        'cart_applied_ttl_minutes' => 30,
    ],
    'voucher' => [
        // Thẻ quà tặng cá nhân (gift card), KHÔNG phải coupon marketing.
        // status values khớp DB-level `voucher.status TINYINT UNSIGNED`.
        'status' => [
            'active'      => 1, // còn dùng được
            'expired'     => 2, // hết HSD (date_expire < now)
            'fully_used'  => 3, // balance = 0
            'revoked'     => 4, // admin thu hồi (fraud)
        ],
        // history_status track lifecycle redeem:
        'history_status' => [
            'applied'   => 1, // đang ở cart, chưa thanh toán
            'confirmed' => 2, // order paid → balance trừ thật
            'refunded'  => 3, // order cancel → balance trả lại
        ],
        'cache' => [
            'tag_root'      => 'voucher_root',
            'key_active'    => 'voucher_active',
            'tag_user_'     => 'voucher_user_', // concat user_email → per-user tag.
        ],
    ],
    'gift' => [
        // trigger_type values khớp DB-level `gift.trigger_type TINYINT UNSIGNED`.
        'trigger_type' => [
            'min_subtotal'         => 1, // đơn từ X VND (đọc gift.min_subtotal).
            'buy_specific_product' => 2, // mua bất kỳ SP trong gift_trigger_product.
        ],
        // pick_type — UX user chọn gift:
        'pick_type' => [
            'auto'          => 0, // tự áp tất cả gift_item khi đủ ĐK.
            'pick_1_of_n'   => 1, // radio: chọn 1 trong N gift_item.
            'pick_up_to_n'  => 2, // checkbox: chọn tối đa pick_limit gift_item.
        ],
        'cache' => [
            'tag_root'   => 'gift_root',
            'key_active' => 'gift_active',
        ],
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
    ],
    'stock' => [
        'policy' => [
            'deny'      => 0,
            'backorder' => 1,
            'untracked' => 2,
        ],
        'movement_type' => [
            'receive'         => 'receive',
            'sale'            => 'sale',
            'sale_backorder'  => 'sale_backorder', // sale that drove on_hand negative
            'reserve'         => 'reserve',
            'release'         => 'release',
            'adjust'          => 'adjust',
            'transfer'        => 'transfer',
        ],
        'default_warehouse_id' => 1,
    ],
    'review' => [
        'status' => [
            'pending' => 0,
            'approved' => 1,
            'rejected' => 2,
            'hidden' => 3,
        ],
        'policy' => [
            'public'   => 'public',
            'login'    => 'login',
            'purchase' => 'purchase',
        ],
        'default_policy' => 'public',
    ],
    'review_report' => [
        'status' => [
            'pending' => 0,
            'resolved' => 1,
            'rejected' => 2
        ]
    ]
];
