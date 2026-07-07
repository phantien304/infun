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
        // Phải có prefix /account để khớp route `account/verify-email`
        // (VerifyEmailJob build link xác thực từ key này).
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
            'sender' => 'phanvantien0204@gmail.com'
        ],
        'forgot_password' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'phanvantien0204@gmail.com'
        ],
        'order_create' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'phanvantien0204@gmail.com',
        ],
        'contact_create' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio',
        ],
        'consult_sign' => [
            'from' => 'phanvantien0204@gmail.com',
            'sender' => 'In&Fun Studio',
        ],
    ],
    'seo' => [
        'home' => [
            'title' => 'Callmeduy',
            'description' => 'Callmeduy desc'
        ],
        'product_list' => [
            'title' => 'Sản phẩm',
            'description' => 'Sản phẩm desc'
        ],
        'blog_list' => [
            'title' => 'Bài viết',
            'description' => 'Chào các bạn, đây là blog của CallmeDuy, được Duy xây dựng như công cụ hỗ trợ giúp mọi người chủ động phân tích, tìm hiểu mỹ phẩm và bước đầu là dựa trên thành phần của sản phẩm. Đừng quên đồng hành cùng Duy ở các kênh social khác để tìm hiểu thông tin về mỹ phẩm nhé !'
        ],
        'ingredient_list' => [
            'title' => 'Thành phần',
            'description' => 'Thành phần'
        ],
        'blog_tag_list' => [
            'title' => 'Tags',
            'description' => 'Tags'
        ]
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
    'emotion' => [
        'text_emotion' => [
            1 => 'Tệ',
            2 => 'Không tốt',
            3 => 'Bình thường',
            4 => 'Tốt',
            5 => 'Rất tốt',
        ],
        'background' => [
            1 => '#0071bc',
            2 => '#0071bc',
            3 => '#8cc63f',
            4 => '#f15a24',
            5 => '#d0021b',
        ]
    ],
    'safety' => [
        'high' => [7, 8, 9],
        'medium' => [4, 5, 6],
        'low' => [1, 2, 3],
    ],
    'sort_by' => [
        'created_at' => [
            'DESC' => 'Mới nhất',
            'ASC' => 'Cũ nhất',
        ],
        'date_end' => [
            'DESC' => 'Hết hạn (Mới nhất)',
            'ASC' => 'Hết hạn (Cũ nhất)',
        ],
        'price' => [
            'DESC' => 'Giá giảm dần',
            'ASC' => 'Giá tăng dần',
        ]
    ],
    'paginate' => [
        'blog_category' => 21
    ]
];
