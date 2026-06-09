<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Filter;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\FilterRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FilterRepository extends QueryableRepository implements FilterRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Filter::class;
    }

    /**
     * Filters load mọi page render → `rememberSystem` luôn cache.
     */
    public function listAllCached(): Collection
    {
        return $this->rememberSystem(
            setting('cache.filters'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.filters'));
    }

    protected function withRelations(): array
    {
        return [
            'description',
            'filterValues.description'
        ];
    }
}
