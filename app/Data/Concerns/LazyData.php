<?php

namespace App\Data\Concerns;

use Spatie\LaravelData\Lazy;

trait LazyData
{
    protected function resolveLazy(Lazy|string|null $value): string
    {
        return (string) ($value instanceof Lazy ? $value->resolve() : $value);
    }
}
