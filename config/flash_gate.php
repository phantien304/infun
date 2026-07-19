<?php

/*
|------------------------------------------------------------------------------
| Flash-sale gate — Redis admission (docs/FLASH-GATE.md, SCALE-30K mục 6)
|------------------------------------------------------------------------------
| Gate là cửa admission TRƯỚC transaction DB của reserveCheckout: variant được
| seed (opt-in) mà hết suất → từ chối ngay bằng 1 lệnh Redis, không xếp hàng
| FOR UPDATE trên row product_stock. DB lock vẫn là tầng chống oversell cuối.
| Nguyên tắc: FAIL-OPEN — Redis lỗi / key không tồn tại → đi đường DB như cũ.
*/

return [

    // Công tắc tổng. Tắt → tryAcquire luôn trả null (đường DB thuần).
    // LƯU Ý vận hành: tắt giữa chừng khi key còn sống → chạy flash-gate:teardown
    // để dọn key (release vẫn credit theo key tồn tại, không theo cờ này).
    'enabled' => (bool) env('FLASH_GATE_ENABLED', false),

    // Connection trong config/database.php ('redis'). BẮT BUỘC instance
    // noeviction (default = session/queue, có AOF). TUYỆT ĐỐI không trỏ 'cache'
    // — allkeys-lru sẽ evict key gate giữa sale → gate biến mất (fail-open),
    // toàn bộ traffic dồn lại vào DB lock đúng lúc nóng nhất.
    'redis_connection' => env('FLASH_GATE_REDIS_CONNECTION', 'default'),

    // Key quota: {prefix}{variant_id}. Index variant đang gated: {prefix}index
    // (SET — để status/reconcile không phải SCAN).
    'key_prefix' => 'gate:stock:',

    // Log channel cho cảnh báo fail-open / drift reconcile. null = stack mặc định.
    'log_channel' => env('FLASH_GATE_LOG_CHANNEL'),

    // Reconcile clamp xuống nhiều hơn ngưỡng này → log warning (nghi leak
    // credit/debit hoặc CMS sửa tồn giữa sale mà chưa re-seed).
    'reconcile_drift_warn' => (int) env('FLASH_GATE_DRIFT_WARN', 5),

];
