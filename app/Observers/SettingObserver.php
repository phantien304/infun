<?php

namespace App\Observers;

use App\Helpers\CacheGate;
use App\Models\Entities\Setting;
use App\Services\ConfigDbService;

/**
 * Setting đổi → 2 hành động bắt buộc:
 *
 *  1. Flush ConfigDbService cache — nếu không, `setting('xxx')` còn trả value
 *     cũ tới 30 ngày (TTL mặc định). User CMS sửa setting xong refresh trang
 *     vẫn thấy value cũ → ngơ ngác debug.
 *
 *  2. Nếu user vừa đổi 1 trong 3 cờ cache (`config_debug` / `config_redis_cache`
 *     / `config_cache_file`) → `CacheGate::store()` có thể flip driver (vd
 *     redis→file, hoặc tắt cache mọi cờ). Cache cũ trong redis vẫn nằm đó,
 *     nhưng app không còn đọc redis nữa → orphan. Gọi `CacheGate::flushAll()`
 *     wipe sạch (chỉ áp dụng khi cờ TRƯỚC khi đổi đang redis; nếu trước file
 *     thì file cache stale TTL — TODO bổ sung file flush).
 *
 * Lưu ý chain: `flushSettingCache()` chạy TRƯỚC `flushAll()` — `flushAll`
 * cần `setting('config_redis_cache')` hiện thời để biết có flush được không;
 * value đó vẫn là value MỚI (model Setting đã saved). Nếu user TẮT redis (1→0)
 * và `flushAll()` đọc cờ mới = 0 → no-op → redis orphan. Trade-off chấp nhận:
 * vẫn an toàn vì app không đọc orphan đó nữa; chạy `redis-cli FLUSHDB` thủ
 * công nếu muốn dọn.
 */
class SettingObserver
{
    public function saved(Setting $setting): void
    {
        $this->invalidate($setting);
    }

    public function deleted(Setting $setting): void
    {
        $this->invalidate($setting);
    }

    protected function invalidate(Setting $setting): void
    {
        try {
            app(ConfigDbService::class)->clearCache();
        } catch (\Throwable $e) {
            logError('SettingObserver clearConfig: ' . $e->getMessage());
        }

        // Chỉ gọi flushAll khi key đổi là 1 trong 3 cờ cache — tránh nuke
        // toàn bộ cache cho mọi setting save (vd config_name, config_email).
        $cacheGateKeys = ['config_debug', 'config_redis_cache', 'config_cache_file'];
        if (in_array($setting->key ?? '', $cacheGateKeys, true)) {
            CacheGate::flushAll();
        }
    }
}
