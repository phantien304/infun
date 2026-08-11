<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\LanguageRepositoryInterface;
use App\Services\Currency\CurrencyService;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected LanguageRepositoryInterface $languageRepo,
    ) {
    }

    public function currency(string $code): RedirectResponse
    {
        $currency = $this->currencyService->findCurrency($code);
        if ($currency) {
            $cookie = (string) getCoreConfig('currency.cookie', 'currency');
            // Host-only bất kể session.domain (CMS/Sanctum), 400 ngày như
            // cookie()->forever() gốc — xem queueHostOnlyCookie() trong
            // app/Common/Common.php.
            queueHostOnlyCookie($cookie, strtoupper($code), 576000);
        }

        return back();
    }

    public function language(string $code): RedirectResponse
    {
        if ($this->isAllowedLanguage($code)) {
            $cookie = (string) getCoreConfig('language.cookie', 'language');
            queueHostOnlyCookie($cookie, strtolower($code), 576000);
        }

        return back();
    }

    protected function isAllowedLanguage(string $code): bool
    {
        $code = strtolower($code);

        $allowed = (array) getCoreConfig('language.allowed', []);
        if (! empty($allowed)) {
            return in_array($code, array_map('strtolower', $allowed), true);
        }

        return $this->languageRepo->listAllCached()
            ->contains(fn ($lang) => strtolower((string) ($lang->code ?? '')) === $code);
    }
}
