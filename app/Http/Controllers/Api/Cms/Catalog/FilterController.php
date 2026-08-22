<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\FilterData;
use App\Exceptions\FilterValueInUseException;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\FilterRequest;
use App\Models\Entities\Filter;
use App\Repositories\Interfaces\FilterRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class FilterController extends BaseCmsController
{
    protected string $permission = 'filter';

    public function __construct(
        private readonly FilterRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return FilterData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(FilterRequest $request)
    {
        try {
            $filter = $this->repo->saveFromCms(null, $request->validated());
        } catch (FilterValueInUseException $e) {
            return respondUnprocessable($e->getMessage());
        }

        return respondCreated(FilterData::fromModel($filter), 'filter_created');
    }

    public function show(Filter $filter)
    {
        $filter->load(['descriptions', 'filterValues.descriptions']);

        return respondSuccess(FilterData::fromModel($filter));
    }

    public function update(FilterRequest $request, Filter $filter)
    {
        try {
            $filter = $this->repo->saveFromCms($filter, $request->validated());
        } catch (FilterValueInUseException $e) {
            return respondUnprocessable($e->getMessage());
        }

        return respondSuccess(FilterData::fromModel($filter), 'filter_updated');
    }

    public function destroy(Filter $filter)
    {
        $this->repo->deleteByIds([$filter->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $filter = $this->repo->restoreById((int) $id);
        abort_if($filter === null, 404);

        return respondSuccess(FilterData::fromModel($filter), 'filter_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->repo->deleteByIds($data['ids'])
            : $this->repo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
