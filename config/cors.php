<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|
*/

return [

    // rcms/* = API CMS admin; api/v1/* = API app khách hàng; vcms/* = upload web.
    // App mobile NATIVE không cần CORS (chỉ trình duyệt mới áp dụng) — để api/v1/*
    // ở đây là cho trường hợp sau này có web/PWA khách hàng gọi API.
    'paths' => ['rcms/*', 'api/v1/*', 'vcms/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', implode(',', [
            'http://cms.infun.test',
            'https://cms.infun.test',
            'http://cms.infun.co',
            'https://cms.infun.co',
        ]))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
