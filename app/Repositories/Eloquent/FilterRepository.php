<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Filter;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\FilterRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class FilterRepository extends QueryableRepository implements FilterRepositoryInterface
{
    use CacheableRepository;
    public function model(): string
    {
        return Filter::class;
    }
    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.filters'),
            fn() => $this->listAll()
        );
    }
    protected function withRelations(): array
    {
        return [
            'description',
            'values.description'
        ];
    }
}
