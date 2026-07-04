<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Language;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\LanguageRepositoryInterface;
use Illuminate\Support\Collection;

class LanguageRepository extends QueryableRepository implements LanguageRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Language::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.languages'),
            fn () => $this->resetModel()->orderBy('id')->get()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.languages'));
    }
}
