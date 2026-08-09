<?php

namespace App\Observers;

use App\Helpers\CacheGate;
use App\Models\Entities\Setting;
use App\Services\ConfigDbService;

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
        $cacheGateKeys = ['config_debug', 'config_redis_cache', 'config_cache_file'];
        if (in_array($setting->key ?? '', $cacheGateKeys, true)) {
            CacheGate::flushAll();
        }
    }
}
