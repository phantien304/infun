<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\SkincareData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\SkincareRequest;
use App\Models\Entities\Skincare;
use App\Repositories\Interfaces\SkincareRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class SkincareController extends BaseCmsController
{
    protected string $permission = 'skincare';

    public function __construct(
        private readonly SkincareRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return SkincareData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAll()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        return respondSuccess($data);
    }

    public function store(SkincareRequest $request)
    {
        $skincare = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(SkincareData::fromModel($skincare), 'skincare_created');
    }

    public function show(Skincare $skincare)
    {
        return respondSuccess(SkincareData::fromModel($skincare));
    }

    public function update(SkincareRequest $request, Skincare $skincare)
    {
        $skincare = $this->repo->saveFromCms($skincare, $request->validated());

        return respondSuccess(SkincareData::fromModel($skincare), 'skincare_updated');
    }

    public function destroy(Skincare $skincare)
    {
        $this->repo->deleteByIds([$skincare->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $skincare = $this->repo->restoreById((int) $id);
        abort_if($skincare === null, 404);

        return respondSuccess(SkincareData::fromModel($skincare), 'skincare_restored');
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
