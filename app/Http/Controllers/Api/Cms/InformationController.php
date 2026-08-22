<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\InformationData;
use App\Http\Requests\Cms\InformationRequest;
use App\Models\Entities\Information;
use App\Repositories\Interfaces\InformationRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class InformationController extends BaseCmsController
{
    protected string $permission = 'information';

    public function __construct(
        private readonly InformationRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return InformationData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(InformationRequest $request)
    {
        $information = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(InformationData::fromModel($information), 'information_created');
    }

    public function show(Information $information)
    {
        $information->load(['descriptions']);

        return respondSuccess(InformationData::fromModel($information));
    }

    public function update(InformationRequest $request, Information $information)
    {
        $information = $this->repo->saveFromCms($information, $request->validated());

        return respondSuccess(InformationData::fromModel($information), 'information_updated');
    }

    public function destroy(Information $information)
    {
        $this->repo->deleteByIds([$information->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $information = $this->repo->restoreById((int) $id);
        abort_if($information === null, 404);

        return respondSuccess(InformationData::fromModel($information), 'information_restored');
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
