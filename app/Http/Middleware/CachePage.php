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

    /**
     * Param tracking (affiliate/UTM/ads) bị loại khỏi cache key: giá trị unique
     * per-visitor (aff_click token...) mà giữ trong key thì mỗi click short link
     * tạo 1 bản cache 24h (rác store) và khách affiliate luôn MISS.
     * Nội dung trang không phụ thuộc các param này nên strip là an toàn.
     */
    protected $ignoredQueryParams = [
        'aff', 'aff_click', 'ref',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid',
    ];

    public function handle($request, Closure $next)
    {
        if (!$request->isMethod('get') || auth()->check()) {
            return $next($request);
        }

        $store = CacheGate::store();
        if (! $store) {
            return $next($request);
        }

        foreach ($this->except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
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
                    'X-Cache-Device' => $device
                ]);
        }
        $response = $next($request);

        if ($response->getStatusCode() === 200 && $this->shouldCache($response)) {
            $content = $this->minifyHtml($response->getContent());
            $store->put($key, $content, now()->addHours(24));
        }

        return $response->header('X-Cache', 'MISS');
    }

    /** URL làm cache key: bỏ param tracking, sort param còn lại (ổn định thứ tự). */
    protected function normalizedUrl($request): string
    {
        $query = $request->query();
        if (! is_array($query)) {
            $query = [];
        }
        $query = array_diff_key($query, array_flip($this->ignoredQueryParams));
        ksort($query);

        return $request->url() . (empty($query) ? '' : '?' . http_build_query($query));
    }

    protected function shouldCache($response)
    {
        return $response->getStatusCode() === 200
            && str_contains($response->headers->get('Content-Type'), 'text/html');
    }

    protected function minifyHtml($html)
    {
        $search = ['/(\n|^)(\x20|\t)+/', '/(\n|^)\s*/', '/\s+(\n|$)/', '/\n+/'];
        $replace = ["\n", "\n", "\n", "\n"];
        return preg_replace($search, $replace, $html);
    }
}
