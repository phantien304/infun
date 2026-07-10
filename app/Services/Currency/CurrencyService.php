<?php

namespace App\Services\Currency;

use App\Models\Entities\Currency;
use App\Repositories\Interfaces\CurrencyRepositoryInterface;
use Illuminate\Support\Collection;

class CurrencyService
{
    private ?Collection $allMemo = null;

    private ?Currency $baseMemo = null;

    private ?Currency $currentMemo = null;

    public function __construct(protected CurrencyRepositoryInterface $repo)
    {
    }

    public function currentCurrency(): Currency
    {
        if ($this->currentMemo !== null) {
            return $this->currentMemo;
        }

        $code = (string) request()->cookie((string) getCoreConfig('currency.cookie', 'currency'), '');
        if ($code === '' || strtoupper($code) === $this->baseCurrencyCode()) {
            return $this->currentMemo = $this->baseCurrency();
        }

        $pickedCurrency = $this->findCurrency($code);

        return $this->currentMemo = ($pickedCurrency ?: $this->baseCurrency());
    }

    public function currentCurrencyCode(): string
    {
        return strtoupper((string) $this->currentCurrency()->code);
    }

    public function allCurrency()
    {
        return $this->allMemo ??= $this->repo->listAllCached();
    }

    public function baseCurrencyCode(): string
    {
        return strtoupper((string) getCoreConfig('currency.base_code', 'VND'));
    }

    public function findCurrency(?string $code): ?Currency
    {
        if (! filled($code)) {
            return null;
        }
        $code = strtoupper($code);

        return $this->allCurrency()->first(fn (Currency $c) => strtoupper((string) $c->code) === $code);
    }

    public function isCurrencyBase(?Currency $currency): bool
    {
        return $currency !== null && strtoupper((string) $currency->code) === $this->baseCurrencyCode();
    }

    public function baseCurrency(): Currency
    {
        if ($this->baseMemo !== null) {
            return $this->baseMemo;
        }

        $baseCode = $this->findCurrency($this->baseCurrencyCode());
        $currency = $baseCode ? clone $baseCode : $this->synthesizeBase();

        $currency->value = 1;
        $symbol = trim((string) getConfigDb('config_currency'));
        if ($symbol !== '') {
            $currency->symbol_left = '';
            $currency->symbol_right = $symbol;
        }

        return $this->baseMemo = $currency;
    }

    protected function synthesizeBase(): Currency
    {
        $currency = new Currency();
        $currency->code = $this->baseCurrencyCode();
        $currency->value = 1;
        $currency->decimal_place = 0;
        $currency->symbol_left = '';
        $currency->symbol_right = trim((string) getConfigDb('config_currency')) ?: '₫';

        return $currency;
    }

    public function convertPrice(float $price, ?Currency $currency = null): float
    {
        $currency ??= $this->currentCurrency();
        $dp = (int) ($currency->decimal_place ?? 0);

        if ($this->isCurrencyBase($currency)) {
            return round($price, $dp);
        }

        return round($price * (float) ($currency->value ?: 1), $dp);
    }

    public function formatPrice(float $price, ?Currency $currency = null): string
    {
        $currency ??= $this->currentCurrency();

        $amount = $this->convertPrice($price, $currency);
        $number = number_format(
            $amount,
            (int) ($currency->decimal_place ?? 0),
            (string) getCoreConfig('currency.decimal_separator', '.'),
            (string) getCoreConfig('currency.thousand_separator', ','),
        );

        $left = trim((string) ($currency->symbol_left ?? ''));
        $right = trim((string) ($currency->symbol_right ?? ''));
        if ($left !== '' && $left === $right) {
            $left = '';
        }

        return $left . $number . $right;
    }

    public function formatSnapshot(float $price, ?string $currencyCode, float|int|null $currencyValue): string
    {
        $code = strtoupper((string) $currencyCode);

        if ($code === '' || $code === $this->baseCurrencyCode()) {
            return $this->formatPrice($price, $this->baseCurrency());
        }

        $currency = $this->findCurrency($code);
        if (! $currency) {
            return $this->formatPrice($price, $this->baseCurrency());
        }
        $currency->value = (float) ($currencyValue ?: ($currency->value ?: 1));

        return $this->formatPrice($price, $currency);
    }
}
