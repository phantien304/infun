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

    protected array $cacheableParams = ['page', 'per_page', 'sort', 'filter'];

    protected array $cacheableFilterKeys = ['category_id', 'manufacturer_id', 'filter_value_id'];

    public function handle($request, Closure $next)
    {
        if (! $request->isMethod('get') || auth()->check()) {
            return $next($request);
        }

        $store = CacheGate::pageStore();
        if (! $store) {
            return $next($request);
        }

        $query = array_diff_key($request->query(), array_flip($this->ignoredQueryParams));

        if ($this->isExcepted($request) || ! $this->isCacheableQuery($query)) {
            return $next($request)->header('X-Cache', 'BYPASS');
        }

        $key = $this->cacheKey($request, $query);

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

    protected function isCacheableQuery(array $query): bool
    {
        if (empty($query)) {
            return true;
        }

        foreach ($query as $key => $value) {
            switch ($key) {
                case 'page':
                case 'per_page':
                    if (! $this->isDigit($value)) {
                        return false;
                    }
                    break;
                case 'sort':
                    if (! is_string($value) || ! preg_match('/^-?[a-z_]+$/i', $value)) {
                        return false;
                    }
                    break;
                case 'filter':
                    if (! $this->isCacheableFilter($value)) {
                        return false;
                    }
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    protected function isCacheableFilter($filter): bool
    {
        if (! is_array($filter) || empty($filter)) {
            return false;
        }

        foreach ($filter as $key => $value) {
            if (! in_array($key, $this->cacheableFilterKeys, true)) {
                return false;
            }
            if (! $this->isDigitOrDigitList($value)) {
                return false;
            }
        }

        return true;
    }

    protected function isDigit($value): bool
    {
        return is_string($value) && $value !== '' && ctype_digit($value);
    }

    protected function isDigitOrDigitList($value): bool
    {
        if ($this->isDigit($value)) {
            return true;
        }
        if (! is_array($value) || empty($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (! $this->isDigit($item)) {
                return false;
            }
        }

        return true;
    }

    protected function cacheKey($request, array $query): string
    {
        $locale   = app()->getLocale();
        $currency = strtoupper((string) $request->cookie(
            (string) getCoreConfig('currency.cookie', 'currency'),
            (string) getCoreConfig('currency.base_code', 'VND'),
        ));

        $signature = $request->path() . '|' . $locale . '|' . $currency;
        if (! empty($query)) {
            $signature .= '|' . $this->normalizeQuery($query);
        }

        return 'pc:' . md5($signature);
    }

    protected function normalizeQuery(array $query): string
    {
        $this->normalizeNode($query);

        return http_build_query($query);
    }

    protected function normalizeNode(array &$node): void
    {
        foreach ($node as &$child) {
            if (is_array($child)) {
                $this->normalizeNode($child);
            }
        }
        unset($child);

        if (array_is_list($node)) {
            sort($node);
        } else {
            ksort($node);
        }
    }
}
