<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\BlogCategory;
use App\Models\Entities\BlogCategoryDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            fn () => $this->listAll()
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

    public function findWithDescription(int|string $id): ?BlogCategory
    {
        return $this->resetModel()->with('description')->find($id);
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'blog_category_description.title' : 'blog_category.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('blog_category_description', function ($join) use ($lang) {
                $join->on('blog_category_description.category_id', '=', 'blog_category.id')
                    ->where('blog_category_description.language_code', '=', $lang);
            })
            ->select('blog_category.*', 'blog_category_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('blog_category_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?BlogCategory
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?BlogCategory $category, array $data): BlogCategory
    {
        return DB::transaction(function () use ($category, $data) {
            $category ??= new BlogCategory();
            $category->parent_id = $this->nullableInt($data['parent_id'] ?? null);
            $category->banner_id = $this->nullableInt($data['banner_id'] ?? null);
            $category->icon      = $data['icon'] ?? null;
            $category->image     = $data['image'] ?? null;
            $category->save();

            foreach ((array) ($data['blog_category_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = BlogCategoryDescription::where('category_id', $category->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new BlogCategoryDescription();
                    $desc->category_id      = $category->id;
                    $desc->language_code    = $code;
                    $desc->title            = $item['title'];
                    $desc->description      = $item['description'] ?? null;
                    $desc->slug             = $item['slug'] ?? null;
                    $desc->meta_title       = $item['meta_title'] ?? null;
                    $desc->meta_description = $item['meta_description'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            $this->flushCache();

            return $category->load('descriptions');
        });
    }

    public function deleteByIds(array $ids): int
    {
        $affected = $this->resetModel()->whereIn('id', $ids)->delete();
        $this->flushCache();

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        $affected = $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
        $this->flushCache();

        return $affected;
    }

    public function restoreById(int $id): ?BlogCategory
    {
        $category = $this->resetModel()->withTrashed()->find($id);
        $category?->restore();
        $this->flushCache();

        return $category?->load('descriptions');
    }
}
