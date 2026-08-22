<?php

return [
    'action_create_or_update' => 'save',
    'storage_domain' => 'http://infunstudio.co/',
    'time_cookie' => 525600,
    'language_default' => 'vi',
    'page_size' => 50,
    'relevance_default' => 0.2,
    'max_row' => ['page' => 18, 'related' => 3],
    'no_img' => 'cms/images/assets/no-image.jpg',
    'img_default' => 'data/banner/2021-12-17/infun-thiet-ke-dau-theo-yeu-cau.jpg',
    'url' => [
        'forgot_password' => '/account/change-password?token=',
        'verify_email' => '/account/verify-email?token=',
        'product' => 'p',
        'category' => 'c',
        'manufacturer' => 'm',
        'information' => 'i',
        'blog_category' => 'bc',
        'blog' => 'n',
        'store_review' => 'sr',
        'tag' => 't'
    ],
    'pagination' => [
        'prev_label' => '<',
        'next_label' => '>',
    ],
    'job_mailer' => [
        'verify_email' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio'
        ],
        'forgot_password' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio'
        ],
        'order_create' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio',
        ],
        'contact_create' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio',
        ],
        'consult_sign' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio',
        ],
        'voucher' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio',
        ],
        'marketing' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio',
        ],
    ],
    'product' => [
        'text_instock' => 'Còn hàng',
        'text_backorder' => 'Đặt trước - giao sau',
        'text_outstock' => 'Hết hàng',
        'text_contact' => 'Liên hệ',
        'badge' => [
            'new' => 'Mới',
            'hot' => 'Bán chạy',
            'sale' => 'Sale',
            'best' => 'Tốt nhất',
        ]
    ],
    'paginate' => [
        'blog_category' => 21
    ]
];
