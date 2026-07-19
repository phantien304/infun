<?php

namespace App\Helpers;

use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class CacheGate
{
    public const GLOBAL_TAG = 'cache_gate_redis';

    public const PAGE_TAG = 'page_cache';

    public static function store(): ?Repository
    {
        if ((int) setting('config_debug') === 1) {
            return null;
        }
        if ((int) setting('config_redis_cache') === 1) {
            return Cache::store('redis')->tags([self::GLOBAL_TAG]);
        }
        if ((int) setting('config_cache_file') === 1) {
            return Cache::store('file');
        }

        return null;
    }

    public static function pageStore(): ?Repository
    {
        if ((int) setting('config_debug') === 1) {
            return null;
        }
        if ((int) setting('config_redis_cache') === 1) {
            return Cache::store('redis')->tags([self::GLOBAL_TAG, self::PAGE_TAG]);
        }
        if ((int) setting('config_cache_file') === 1) {
            return Cache::store('file');
        }

        return null;
    }

    public static function flushPages(): void
    {
        if ((int) setting('config_redis_cache') !== 1) {
            return;
        }
        try {
            Cache::store('redis')->tags([self::PAGE_TAG])->flush();
        } catch (\Throwable $e) {
            logError('CacheGate::flushPages: ' . $e->getMessage());
        }
    }

    public static function globalTags(): array
    {
        if ((int) setting('config_redis_cache') === 1) {
            return [self::GLOBAL_TAG];
        }

        return [];
    }

    public static function supportsTags(?Repository $store = null): bool
    {
        $store ??= self::store();
        if (! $store) {
            return false;
        }

        return $store->getStore() instanceof TaggableStore;
    }

    public static function flushAll(): void
    {
        if ((int) setting('config_redis_cache') !== 1) {
            return;
        }
        try {
            Cache::store('redis')->tags([self::GLOBAL_TAG])->flush();
        } catch (\Throwable $e) {
            logError('CacheGate::flushAll: ' . $e->getMessage());
        }
    }

    public static function systemStore(): Repository
    {
        if ((int) setting('config_redis_cache') === 1) {
            return Cache::store('redis')->tags([self::GLOBAL_TAG]);
        }
        if ((int) setting('config_cache_file') === 1) {
            return Cache::store('file');
        }

        return Cache::store();
    }
}
