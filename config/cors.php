<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Cho phép CMS chạy ở origin riêng (cms.infun.test) gọi API của Laravel
| ở infun.test. Laravel 12 đã nạp sẵn middleware HandleCors toàn cục, chỉ
| cần file config này để khai báo origin được phép.
|
| 'paths' = các URL chịu CORS. API CMS nằm dưới /rcms (xem
| VITE_API_BASE_URL của infun_cms) -> khớp 'rcms/*'. Endpoint upload cũ
| '/vcms/file/save' (nếu còn dùng) được thêm tường minh.
|
| supports_credentials = true để sẵn sàng cho auth bằng session-cookie
| (cần withCredentials=true ở frontend + SESSION_DOMAIN=.infun.test).
| Khi bật credentials, allowed_origins KHÔNG được dùng '*', nên ta liệt
| kê origin tường minh bên dưới.
|
*/

return [

    // rcms/* = API CMS admin; api/v1/* = API app khách hàng; vcms/* = upload web.
    // App mobile NATIVE không cần CORS (chỉ trình duyệt mới áp dụng) — để api/v1/*
    // ở đây là cho trường hợp sau này có web/PWA khách hàng gọi API.
    'paths' => ['rcms/*', 'api/v1/*', 'vcms/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://cms.infun.test',
        'https://cms.infun.test',
        'http://cms.infun.co',
        'https://cms.infun.co',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
