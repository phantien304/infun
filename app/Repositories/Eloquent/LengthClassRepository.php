<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\LengthClass;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\LengthClassRepositoryInterface;
use Illuminate\Support\Collection;

class LengthClassRepository extends QueryableRepository implements LengthClassRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return LengthClass::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.length_classes'),
            fn () => $this->resetModel()->with('descriptions')->orderBy('id')->get(),
            perLocale: false,
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.length_classes'), perLocale: false);
    }

    public function getAll(): Collection
    {
        return $this->resetModel()->query()->get();
    }
}
