<?php

namespace App\Repositories\Concerns;

use Illuminate\Support\Facades\Cache;

trait CacheableRepository
{
    protected function rememberCache(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        $cacheKey = $perLocale ? $key . app()->getLocale() : $key;
        return Cache::remember($cacheKey, $ttl ?? now()->addDays(30), $resolver);
    }
    protected function forgetCache(string $key, bool $perLocale = true): void
    {
        if (!$perLocale) {
            Cache::forget($key);
            return;
        }
        foreach (config('app.locales', [app()->getLocale()]) as $locale) {
            Cache::forget($key . $locale);
        }
    }
}
