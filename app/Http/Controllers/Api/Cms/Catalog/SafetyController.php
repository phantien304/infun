<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\SafetyData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\SafetyRequest;
use App\Models\Entities\Safety;
use App\Repositories\Interfaces\SafetyRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class SafetyController extends BaseCmsController
{
    protected string $permission = 'safety';

    public function __construct(
        private readonly SafetyRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return SafetyData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAll()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        return respondSuccess($data);
    }

    public function store(SafetyRequest $request)
    {
        $safety = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(SafetyData::fromModel($safety), 'safety_created');
    }

    public function show(Safety $safety)
    {
        return respondSuccess(SafetyData::fromModel($safety));
    }

    public function update(SafetyRequest $request, Safety $safety)
    {
        $safety = $this->repo->saveFromCms($safety, $request->validated());

        return respondSuccess(SafetyData::fromModel($safety), 'safety_updated');
    }

    public function destroy(Safety $safety)
    {
        $this->repo->deleteByIds([$safety->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $safety = $this->repo->restoreById((int) $id);
        abort_if($safety === null, 404);

        return respondSuccess(SafetyData::fromModel($safety), 'safety_restored');
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
