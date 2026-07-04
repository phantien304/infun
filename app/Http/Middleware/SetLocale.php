<?php

namespace App\Http\Middleware;

use App\Repositories\Interfaces\LanguageRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(protected LanguageRepositoryInterface $languageRepo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $cookie = (string) getCoreConfig('language.cookie', 'language');
        $code = strtolower((string) $request->cookie($cookie, ''));

        if ($code !== '' && $this->isAllowed($code)) {
            app()->setLocale($code);
        }

        return $next($request);
    }

    protected function isAllowed(string $code): bool
    {
        $allowed = (array) getCoreConfig('language.allowed', []);
        if (! empty($allowed)) {
            return in_array($code, array_map('strtolower', $allowed), true);
        }

        return $this->languageRepo->listAllCached()
            ->contains(fn ($lang) => strtolower((string) ($lang->code ?? '')) === $code);
    }
}
