<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Blog;
use App\Models\Entities\BlogDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class BlogRepository extends QueryableRepository implements BlogRepositoryInterface
{
    public function model(): string
    {
        return Blog::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::partial('title', 'blog_description.title'),
            AllowedFilter::exact('category_id', 'blog.category_id'),
            AllowedFilter::exact('featured', 'blog.featured'),
            AllowedFilter::callback('search', function (Builder $query, $value) {
                $query->where(function (Builder $q) use ($value) {
                    $q->where('blog_description.title', 'like', "%{$value}%")
                        ->orWhere('blog_description.description', 'like', "%{$value}%");
                });
            }),
        ];
    }

    protected function allowedSorts(): array
    {
        return [
            'viewed',
            AllowedSort::field('created_at', 'blog.created_at'),
            AllowedSort::field('title', 'blog_description.title'),
        ];
    }

    protected function defaultSort(): string
    {
        return '-blog.created_at';
    }

    protected function sortMenu(): array
    {
        return ['-created_at', 'created_at', 'title', '-title'];
    }

    protected function allowedIncludes(): array
    {
        return ['blogCategory', 'user'];
    }

    protected function withRelations(): array
    {
        return [
            'description',
            'blogCategory.description',
            'user:id,full_name',
        ];
    }

    protected function baseQuery(): Builder
    {
        $query = $this->resetModel()->newQuery();
        $query->select('blog.*')
            ->leftJoin('blog_description', function ($join) {
                $join->on('blog_description.blog_id', '=', 'blog.id')
                    ->where('blog_description.language_code', app()->getLocale());
            });
        return $query;
    }

    public function getBlogLatest(int $limit = 4)
    {
        return $this->resetModel()
            ->with(['description'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getDetail($id): ?Blog
    {
        return $this->resetModel()
            ->with($this->withRelations())
            ->find($id);
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'blog_description.title' : 'blog.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('blog_description', function ($join) use ($lang) {
                $join->on('blog_description.blog_id', '=', 'blog.id')
                    ->where('blog_description.language_code', '=', $lang);
            })
            ->select('blog.*', 'blog_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('blog_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Blog
    {
        return $this->resetModel()->withTrashed()->with(['descriptions', 'user'])->find($id);
    }

    public function saveFromCms(?Blog $blog, array $data): Blog
    {
        return DB::transaction(function () use ($blog, $data) {
            $blog ??= new Blog();
            $blog->category_id = $this->nullableInt($data['category_id'] ?? null);
            $blog->author_id   = $this->nullableInt($data['author_id'] ?? null);
            $blog->image       = $data['image'] ?? null;
            $blog->viewed      = (int) ($data['viewed'] ?? 0);
            $blog->featured    = (int) ($data['featured'] ?? 0);
            $blog->save();

            foreach ((array) ($data['blog_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = BlogDescription::where('blog_id', $blog->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new BlogDescription();
                    $desc->blog_id           = $blog->id;
                    $desc->language_code     = $code;
                    $desc->title             = $item['title'];
                    $desc->description       = $item['description'] ?? null;
                    $desc->content           = $item['content'] ?? null;
                    $desc->tag               = $item['tag'] ?? null;
                    $desc->meta_title        = $item['meta_title'] ?? null;
                    $desc->meta_description  = $item['meta_description'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $blog->load(['descriptions', 'user']);
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

    public function restoreById(int $id): ?Blog
    {
        $blog = $this->resetModel()->withTrashed()->find($id);
        $blog?->restore();

        return $blog?->load(['descriptions', 'user']);
    }
}
