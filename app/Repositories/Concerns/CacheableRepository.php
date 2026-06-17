<?php

namespace App\Repositories\Concerns;

use App\Helpers\CacheGate;
use Illuminate\Database\Eloquent\Model;

trait CacheableRepository
{
    protected function rememberCache(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true, array $tags = [])
    {
        $store = CacheGate::store();
        if (! $store) {
            return $resolver();
        }
        return $this->doRemember($store, $key, $resolver, $ttl, $perLocale, $tags);
    }

    protected function rememberSystem(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        return $this->doRemember(CacheGate::systemStore(), $key, $resolver, $ttl, $perLocale, []);
    }

    protected function rememberEntity(
        Model $entity,
        string $prefix,
        \Closure $resolver,
        $ttl = null,
        array $tags = [],
        ?bool $perLocale = null,
    ) {
        $suffix = method_exists($entity, 'getKeyAsString')
            ? $entity->getKeyAsString()
            : (string) $entity->getKey();
        $perLocale ??= ! is_array($entity->getKeyName());
        return $this->rememberCache($prefix . $suffix, $resolver, $ttl, $perLocale, $tags);
    }

    protected function forgetCache(string $key, bool $perLocale = true): void
    {
        $store = CacheGate::store();
        if (! $store) {
            return;
        }
        $this->eachLocale($key, $perLocale, fn (string $k) => $store->forget($k));
    }

    protected function forgetCacheTagged(array $tags): void
    {
        $store = CacheGate::store();
        if (! $store || ! CacheGate::supportsTags($store)) {
            return;
        }
        $store->getStore()->tags(array_merge(CacheGate::globalTags(), $tags))->flush();
    }

    protected function forgetSystem(string $key, bool $perLocale = true): void
    {
        $store = CacheGate::systemStore();
        $this->eachLocale($key, $perLocale, fn (string $k) => $store->forget($k));
    }

    private function doRemember($store, string $key, \Closure $resolver, $ttl, bool $perLocale, array $tags)
    {
        $key = $this->localized($key, $perLocale);
        $ttl ??= $this->defaultTtl();
        if (! $tags || ! CacheGate::supportsTags($store)) {
            return $store->remember($key, $ttl, $resolver);
        }
        $allTags = array_merge(CacheGate::globalTags(), $tags);
        return $store->getStore()->tags($allTags)->remember($key, $ttl, $resolver);
    }

    private function defaultTtl(): \DateTimeInterface
    {
        return now()->addDays(30);
    }

    private function localized(string $key, bool $perLocale): string
    {
        return $perLocale ? $key . app()->getLocale() : $key;
    }

    private function eachLocale(string $key, bool $perLocale, callable $forget): void
    {
        if (! $perLocale) {
            $forget($key);
            return;
        }
        foreach (config('app.locales', [app()->getLocale()]) as $locale) {
            $forget($key . $locale);
        }
    }
}
