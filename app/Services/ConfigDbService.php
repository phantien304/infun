<?php

namespace App\Services;

use App\Repositories\Interfaces\SettingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ConfigDbService extends BaseService
{
    protected string $keyCache;

    public function __construct(protected SettingRepositoryInterface $settingRepo)
    {
        $this->keyCache = getCoreConfig('cache.setting');
    }

    public function getConfig()
    {
        return Cache::remember($this->keyCache, now()->addDays(30), function () {
            $config = [];
            $settings = $this->settingRepo->listAll();
            foreach ($settings as $item) {
                $config[$item->key] = $this->parseValue($item);
            }
            return $config;
        });
    }

    protected function parseValue($item)
    {
        if ($item->serialized || is_numeric($item->value)) {
            $decoded = json_decode($item->value, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $item->value;
        }

        return $item->value;
    }

    public function clearCache()
    {
        return Cache::forget($this->keyCache);
    }
}
