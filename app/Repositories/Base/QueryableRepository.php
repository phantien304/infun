<?php

namespace App\Repositories\Base;

use App\Repositories\Base\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\QueryBuilder;

abstract class QueryableRepository extends BaseRepository
{
    protected int $defaultPerPage = 20;
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
        return $this->model->newQuery();
    }
    public function list(?Request $request = null, ?int $perPage = null): LengthAwarePaginator
    {
        $request ??= request();

        return $this->buildQuery($request)
            ->paginate($perPage ?? $this->defaultPerPage)
            ->appends($request->query());
    }
    public function listAll(?Request $request = null): Collection
    {
        return $this->buildQuery($request ?? request())->get();
    }
    protected function buildQuery(Request $request): QueryBuilder
    {
        $query = QueryBuilder::for($this->baseQuery(), $request)
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts($this->allowedSorts())
            ->defaultSort($this->defaultSort());

        if ($this->allowedIncludes() !== []) {
            $query->allowedIncludes($this->allowedIncludes());
        }

        if ($this->withRelations() !== []) {
            $query->with($this->withRelations());
        }

        return $query;
    }
}
