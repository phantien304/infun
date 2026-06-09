<?php

namespace App\Repositories\Concerns;

use App\Helpers\CacheGate;

trait CacheableRepository
{
    protected function rememberCache(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        $store = CacheGate::store();
        if (! $store) {
            return $resolver();
        }

        return $store->remember(
            $this->resolveCacheKey($key, $perLocale),
            $ttl ?? now()->addDays(30),
            $resolver
        );
    }

    protected function rememberCacheTagged(array $tags, string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        $store = CacheGate::store();
        if (! $store) {
            return $resolver();
        }

        if (! CacheGate::supportsTags($store)) {
            return $store->remember(
                $this->resolveCacheKey($key, $perLocale),
                $ttl ?? now()->addDays(30),
                $resolver
            );
        }

        $allTags = array_merge(CacheGate::globalTags(), $tags);

        return $store->getStore()->tags($allTags)->remember(
            $this->resolveCacheKey($key, $perLocale),
            $ttl ?? now()->addDays(30),
            $resolver
        );
    }

    protected function forgetCache(string $key, bool $perLocale = true): void
    {
        $store = CacheGate::store();
        if (! $store) {
            return;
        }

        if (! $perLocale) {
            $store->forget($key);

            return;
        }
        foreach (config('app.locales', [app()->getLocale()]) as $locale) {
            $store->forget($key . $locale);
        }
    }

    protected function forgetCacheTagged(array $tags): void
    {
        $store = CacheGate::store();
        if (! $store || ! CacheGate::supportsTags($store)) {
            return;
        }
        $allTags = array_merge(CacheGate::globalTags(), $tags);
        $store->getStore()->tags($allTags)->flush();
    }

    protected function cacheSupportsTags(): bool
    {
        return CacheGate::supportsTags();
    }

    protected function rememberSystem(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        $store = CacheGate::systemStore();

        return $store->remember(
            $this->resolveCacheKey($key, $perLocale),
            $ttl ?? now()->addDays(30),
            $resolver
        );
    }

    protected function forgetSystem(string $key, bool $perLocale = true): void
    {
        $store = CacheGate::systemStore();

        if (! $perLocale) {
            $store->forget($key);

            return;
        }
        foreach (config('app.locales', [app()->getLocale()]) as $locale) {
            $store->forget($key . $locale);
        }
    }

    private function resolveCacheKey(string $key, bool $perLocale): string
    {
        return $perLocale ? $key . app()->getLocale() : $key;
    }
}
