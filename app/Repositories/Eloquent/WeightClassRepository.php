<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\WeightClass;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\WeightClassRepositoryInterface;
use Illuminate\Support\Collection;

class WeightClassRepository extends QueryableRepository implements WeightClassRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return WeightClass::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.weight_classes'),
            fn () => $this->resetModel()->with('descriptions')->orderBy('id')->get(),
            perLocale: false,
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.weight_classes'), perLocale: false);
    }

    public function getAll(): Collection
    {
        return $this->resetModel()->query()->get();
    }
}
