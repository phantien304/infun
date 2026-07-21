<?php

namespace App\Http\Middleware;

use App\Helpers\CacheGate;
use Closure;

class CachePage
{
    protected $except = [
        'cart*',
        'checkout*',
        'account*',
        'api/*',
    ];

    protected $ignoredQueryParams = [
        'aff', 'aff_click', 'ref',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid',
    ];

    protected $allowedQueryParams = [
        'page', 'sort', 'order', 'filter',
        'rating', 'in_stock', 'tag', 'brand', 'manufacturer',
    ];

    protected $maxCacheablePage = 500;

    public function handle($request, Closure $next)
    {
        if (! $request->isMethod('get') || auth()->check()) {
            return $next($request);
        }

        $store = CacheGate::pageStore();
        if (! $store) {
            return $next($request);
        }

        foreach ($this->except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        if ($this->hasDisallowedParams($request) || ! $this->pageWithinCap($request)) {
            return $next($request)->header('X-Cache', 'BYPASS');
        }

        $device = isMobile() ? 'mobile' : 'desktop';
        $locale = app()->getLocale();
        $currency = strtoupper((string) $request->cookie(
            (string) getCoreConfig('currency.cookie', 'currency'),
            (string) getCoreConfig('currency.base_code', 'VND'),
        ));
        $key = 'page_cache_' . md5($this->normalizedUrl($request) . '_' . $device . '_' . $locale . '_' . $currency);

        if ($cachedContent = $store->get($key)) {
            return response($cachedContent)
                ->withHeaders([
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'X-Cache' => 'HIT',
                    'X-Cache-Device' => $device,
                ]);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && $this->shouldCache($response)) {
            $store->put($key, $response->getContent(), now()->addHours(24));
        }

        return $response->header('X-Cache', 'MISS');
    }

    /** Còn param nào (đã trừ tracking) không nằm trong allowlist? */
    protected function hasDisallowedParams($request): bool
    {
        $query = $request->query();
        if (! is_array($query)) {
            $query = [];
        }
        $query = array_diff_key($query, array_flip($this->ignoredQueryParams));
        $extra = array_diff(array_keys($query), $this->allowedQueryParams);

        return ! empty($extra);
    }

    /** `page` (nếu có) phải là số nguyên và <= trần. */
    protected function pageWithinCap($request): bool
    {
        $page = $request->query('page');
        if ($page === null) {
            return true;
        }
        if (is_array($page) || ! ctype_digit((string) $page)) {
            return false;
        }

        return (int) $page >= 1 && (int) $page <= $this->maxCacheablePage;
    }

    protected function normalizedUrl($request): string
    {
        $query = $request->query();
        if (! is_array($query)) {
            $query = [];
        }
        // Chỉ giữ param allowlist (tự động loại tracking + mọi param khác).
        $query = array_intersect_key($query, array_flip($this->allowedQueryParams));
        ksort($query);

        return $request->url() . (empty($query) ? '' : '?' . http_build_query($query));
    }

    protected function shouldCache($response)
    {
        return $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
