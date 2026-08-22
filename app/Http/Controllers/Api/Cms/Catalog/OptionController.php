<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\OptionData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\OptionRequest;
use App\Models\Entities\Option;
use App\Repositories\Interfaces\OptionRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class OptionController extends BaseCmsController
{
    protected string $permission = 'option';

    public function __construct(
        private readonly OptionRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return OptionData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(OptionRequest $request)
    {
        $option = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(OptionData::fromModel($option), 'option_created');
    }

    public function show(Option $option)
    {
        $option->load(['descriptions', 'optionValues.descriptions']);

        return respondSuccess(OptionData::fromModel($option));
    }

    public function update(OptionRequest $request, Option $option)
    {
        $option = $this->repo->saveFromCms($option, $request->validated());

        return respondSuccess(OptionData::fromModel($option), 'option_updated');
    }

    public function destroy(Option $option)
    {
        $this->repo->deleteByIds([$option->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $option = $this->repo->restoreById((int) $id);
        abort_if($option === null, 404);

        return respondSuccess(OptionData::fromModel($option), 'option_restored');
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
