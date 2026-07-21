<?php

namespace App\Http\Middleware;

use App\Helpers\CacheGate;
use Closure;

class CachePage
{
    protected array $except = ['cart*', 'checkout*', 'account*', 'api/*'];

    protected array $ignoredQueryParams = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'aff', 'aff_click', 'ref',
    ];

    public function handle($request, Closure $next)
    {
        if (! $request->isMethod('get') || auth()->check()) {
            return $next($request);
        }

        $store = CacheGate::pageStore();
        if (! $store) {
            return $next($request);
        }

        if ($this->isExcepted($request) || $this->hasMeaningfulQuery($request)) {
            return $next($request)->header('X-Cache', 'BYPASS');
        }

        $key = $this->cacheKey($request);

        if ($cached = $store->get($key)) {
            return response($cached)->withHeaders([
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Cache'      => 'HIT',
            ]);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $store->put($key, $response->getContent(), now()->addHours(24));
        }

        return $response->header('X-Cache', 'MISS');
    }

    protected function isExcepted($request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function hasMeaningfulQuery($request): bool
    {
        return ! empty(array_diff_key($request->query(), array_flip($this->ignoredQueryParams)));
    }

    protected function cacheKey($request): string
    {
        $locale   = app()->getLocale();
        $currency = strtoupper((string) $request->cookie(
            (string) getCoreConfig('currency.cookie', 'currency'),
            (string) getCoreConfig('currency.base_code', 'VND'),
        ));

        return 'pc:' . md5($request->path() . '|' . $locale . '|' . $currency);
    }
}
