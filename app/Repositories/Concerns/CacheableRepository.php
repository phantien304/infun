<?php

namespace App\Repositories\Concerns;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

trait CacheableRepository
{
    protected function rememberCache(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        return Cache::remember(
            $this->resolveCacheKey($key, $perLocale),
            $ttl ?? now()->addDays(30),
            $resolver
        );
    }

    protected function rememberCacheTagged(array $tags, string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        if (!$this->cacheSupportsTags()) {
            return $this->rememberCache($key, $resolver, $ttl, $perLocale);
        }

        return Cache::tags($tags)->remember(
            $this->resolveCacheKey($key, $perLocale),
            $ttl ?? now()->addDays(30),
            $resolver
        );
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

    protected function forgetCacheTagged(array $tags): void
    {
        if ($this->cacheSupportsTags()) {
            Cache::tags($tags)->flush();
        }
    }

    protected function cacheSupportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    private function resolveCacheKey(string $key, bool $perLocale): string
    {
        return $perLocale ? $key . app()->getLocale() : $key;
    }
}
