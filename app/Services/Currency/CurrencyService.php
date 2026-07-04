<?php

namespace App\Services\Currency;

use App\Models\Entities\Currency;
use App\Repositories\Interfaces\CurrencyRepositoryInterface;

class CurrencyService
{
    /** Memo trong 1 request (service là singleton) — tránh truy cache store lặp lại. */
    private ?\Illuminate\Support\Collection $allMemo = null;

    private ?Currency $baseMemo = null;

    public function __construct(protected CurrencyRepositoryInterface $repo)
    {
    }

    public function all()
    {
        return $this->allMemo ??= $this->repo->listAllCached();
    }

    public function baseCode(): string
    {
        return strtoupper((string) getCoreConfig('currency.base_code', 'VND'));
    }

    public function find(?string $code): ?Currency
    {
        if (! filled($code)) {
            return null;
        }
        $code = strtoupper($code);

        return $this->all()->first(fn (Currency $c) => strtoupper((string) $c->code) === $code);
    }

    public function isBase(?Currency $currency): bool
    {
        return $currency !== null && strtoupper((string) $currency->code) === $this->baseCode();
    }

    public function base(): Currency
    {
        if ($this->baseMemo !== null) {
            return $this->baseMemo;
        }

        // clone: không mutate object đang nằm trong collection cache (all()).
        $found = $this->find($this->baseCode());
        $currency = $found ? clone $found : $this->synthesizeBase();

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
        $currency->code = $this->baseCode();
        $currency->value = 1;
        $currency->decimal_place = 0;
        $currency->symbol_left = '';
        $currency->symbol_right = trim((string) getConfigDb('config_currency')) ?: '₫';

        return $currency;
    }

    public function convert(float $amountBase, ?Currency $currency = null): float
    {
        $currency ??= $this->base();
        $dp = (int) ($currency->decimal_place ?? 0);

        if ($this->isBase($currency)) {
            return round($amountBase, $dp);
        }

        return round($amountBase * (float) ($currency->value ?: 1), $dp);
    }

    public function format(float $amountBase, ?Currency $currency = null): string
    {
        $currency ??= $this->base();

        $amount = $this->convert($amountBase, $currency);
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

        return $left.$number.$right;
    }
}
