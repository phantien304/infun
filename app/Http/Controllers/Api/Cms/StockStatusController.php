<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\StockStatusData;
use App\Http\Requests\Cms\StockStatusRequest;
use App\Models\Entities\StockStatus;
use App\Repositories\Interfaces\StockStatusRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class StockStatusController extends BaseCmsController
{
    protected string $permission = 'stock-status';

    public function __construct(
        private readonly StockStatusRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return StockStatusData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listWithDescription()
            ->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->description?->name ?? '',
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(StockStatusRequest $request)
    {
        $status = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(StockStatusData::fromModel($status), 'stock_status_created');
    }

    public function show(StockStatus $stockStatus)
    {
        return respondSuccess(StockStatusData::fromModel($stockStatus->load('descriptions')));
    }

    public function update(StockStatusRequest $request, StockStatus $stockStatus)
    {
        $status = $this->repo->saveFromCms($stockStatus, $request->validated());

        return respondSuccess(StockStatusData::fromModel($status), 'stock_status_updated');
    }

    public function destroy(StockStatus $stockStatus)
    {
        $this->repo->deleteByIds([$stockStatus->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $status = $this->repo->restoreById((int) $id);
        abort_if($status === null, 404);

        return respondSuccess(StockStatusData::fromModel($status), 'stock_status_restored');
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
