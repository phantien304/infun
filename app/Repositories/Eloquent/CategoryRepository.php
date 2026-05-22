<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Category;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository extends QueryableRepository implements CategoryRepositoryInterface
{
    use CacheableRepository;
    public function model(): string
    {
        return Category::class;
    }
    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.categories'),
            fn() => $this->listAll()
        );
    }
    protected function withRelations(): array
    {
        return ['description'];
    }
}
