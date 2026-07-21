<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Category;
use App\Models\Entities\CategoryDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryRepository extends QueryableRepository implements CategoryRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Category::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberSystemModels(
            setting('cache.categories'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.categories'));
        \App\View\FragmentCache::forgetTree();
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

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'category_description.title' : 'category.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1); // -1 tất cả, 1 hiển thị, 0 đã xoá
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = Category::query()
            ->leftJoin('category_description', function ($join) use ($lang) {
                $join->on('category_description.category_id', '=', 'category.id')
                    ->where('category_description.language_code', '=', $lang);
            })
            ->select('category.*', 'category_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('category_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Category
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category ??= new Category();
            $category->parent_id  = (int) ($data['parent_id'] ?? 0);
            $category->sort_order = (int) ($data['sort_order'] ?? 0);
            $category->icon       = $data['icon'] ?? null;
            $category->image      = $data['image'] ?? null;
            $category->image_icon = $data['image_icon'] ?? null;
            $category->save();

            foreach ((array) ($data['category_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = CategoryDescription::where('category_id', $category->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new CategoryDescription();
                    $desc->category_id      = $category->id;
                    $desc->language_code    = $code;
                    $desc->title            = $item['title'];
                    $desc->description      = $item['description'] ?? null;
                    $desc->meta_title       = $item['meta_title'] ?? null;
                    $desc->meta_description = $item['meta_description'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $category->load('descriptions');
        });
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Category
    {
        $category = $this->resetModel()->withTrashed()->find($id);
        $category?->restore();

        return $category?->load('descriptions');
    }

    public function listWithDescription(): Collection
    {
        return $this->resetModel()->with('description')->get();
    }
}
