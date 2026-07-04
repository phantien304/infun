<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Currency;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CurrencyRepositoryInterface;
use Illuminate\Support\Collection;

class CurrencyRepository extends QueryableRepository implements CurrencyRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Currency::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.currencies'),
            fn () => $this->resetModel()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.currencies'));
    }
}
