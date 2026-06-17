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

    /**
     * Categories load mọi page render (Controller::render gắn vào common view
     * data) → dùng `rememberSystem` để luôn cache kể cả khi `config_debug=1`.
     */
    public function listAllCached(): Collection
    {
        return $this->rememberSystem(
            setting('cache.categories'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.categories'));
    }

    public function getCategoryDetail(int $id): ?Category
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()
            ->with($this->withRelations())
            ->find($id);
    }

    protected function withRelations(): array
    {
        return ['description'];
    }
}
