<?php

namespace App\Repositories\Base;

use App\Repositories\Base\BaseRepository;
use App\Repositories\Concerns\HasListFilterToolbar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\QueryBuilder;

abstract class QueryableRepository extends BaseRepository
{
    use HasListFilterToolbar;

    protected int $defaultPerPage = 20;

    protected int $maxPerPage = 200;

    protected function allowedFilters(): array
    {
        return [];
    }

    protected function allowedSorts(): array
    {
        return [];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function allowedIncludes(): array
    {
        return [];
    }

    protected function withRelations(): array
    {
        return [];
    }

    protected function baseQuery(): Builder
    {
        return $this->resetModel()->newQuery();
    }

    protected function beforeBuildForList(Builder $query): Builder
    {
        return $query;
    }

    protected function afterBuildForList(QueryBuilder $query): QueryBuilder
    {
        return $query;
    }

    public function list(?Request $request = null, ?int $perPage = null, ?\Closure $modifyBase = null): LengthAwarePaginator
    {
        $request ??= request();

        $perPage ??= (int) $request->get('per_page', $this->defaultPerPage);
        $perPage = max(1, min($perPage, $this->maxPerPage));

        return $this->buildQueryForList($request, $modifyBase)
            ->paginate($perPage)
            ->appends($request->query());
    }

    public function listAll(?Request $request = null, ?\Closure $modifyBase = null): Collection
    {
        return $this->buildQueryForList($request ?? request(), $modifyBase)->get();
    }

    protected function buildQueryForList(?Request $request = null, ?\Closure $modifyBase = null): QueryBuilder
    {
        $base = $this->beforeBuildForList($this->baseQuery());

        if ($modifyBase !== null) {
            $modifyBase($base);
        }

        $query = QueryBuilder::for($base, $request)
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts($this->allowedSorts())
            ->defaultSort($this->defaultSort());

        if ($this->allowedIncludes() !== []) {
            $query->allowedIncludes($this->allowedIncludes());
        }

        if ($this->withRelations() !== []) {
            $query->with($this->withRelations());
        }

        return $this->afterBuildForList($query);
    }
}
