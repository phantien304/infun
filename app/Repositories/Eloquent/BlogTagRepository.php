<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\BlogTag;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BlogTagRepository extends QueryableRepository implements BlogTagRepositoryInterface
{
    use CacheableRepository;
    public function model(): string
    {
        return BlogTag::class;
    }
    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.blog_tags'),
            fn() => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(getCoreConfig('cache.blog_tags'));
    }

    protected function withRelations(): array
    {
        return [
            'description'
        ];
    }
}
