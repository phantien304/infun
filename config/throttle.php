<?php

/*
|--------------------------------------------------------------------------
| Rate limit cho endpoint nóng (docs/SCALE-30K.md mục B — throttle prod)
|--------------------------------------------------------------------------
| Đơn vị: request / PHÚT / 1 khách (key theo user → session → IP, xem
| AppServiceProvider::registerRateLimiters).
|
| Mặc định = mức prod khuyến nghị. STAGING chạy k6 thì NỚI QUA ENV,
| không sửa code:
|   THROTTLE_ADD_TO_CART=100000 THROTTLE_SAVE_ORDER=100000
| (nhớ config:clear nếu đã config:cache)
*/

return [
    'add_to_cart' => (int) env('THROTTLE_ADD_TO_CART', 30),
    'save_order'  => (int) env('THROTTLE_SAVE_ORDER', 10),
];
