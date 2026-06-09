<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\BlogCategory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BlogCategoryRepository extends QueryableRepository implements BlogCategoryRepositoryInterface
{
    use CacheableRepository;
    public function model(): string
    {
        return BlogCategory::class;
    }
    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.blog_categories'),
            fn() => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(getCoreConfig('cache.blog_categories'));
    }

    protected function withRelations(): array
    {
        return [
            'description'
        ];
    }
}
