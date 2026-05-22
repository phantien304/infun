<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Blog;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
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
    protected function withRelations(): array
    {
        return [
            'description',
            'blogCategory.description',
            'user:id,full_name',
        ];
    }
    protected function allowedIncludes(): array
    {
        return ['blogCategory', 'user'];
    }
    public function getBlogLatest($limit = 4)
    {
        return $this->model
            ->with([
                'description'
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
