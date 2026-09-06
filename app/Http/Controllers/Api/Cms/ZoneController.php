<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\ZoneData;
use App\Http\Requests\Cms\ZoneRequest;
use App\Models\Entities\Zone;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class ZoneController extends BaseCmsController
{
    protected string $permission = 'zone';

    public function __construct(
        private readonly ZoneRepositoryInterface $repo
    ) {
    }

    /**
     * 2 chế độ trên CÙNG 1 route (giống CarrierController::index()):
     *  - KHÔNG có query param nào → giữ NGUYÊN hành vi cũ: mảng phẳng
     *    {id,name} từ cache, không phân trang (checkout dropdown dùng).
     *  - CÓ ít nhất 1 param list chuẩn → màn CMS list mới (FormSearch+Pager).
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return ZoneData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($z) => ['id' => $z->id, 'name' => $z->description?->name ?? ''])
            ->values();

        return respondSuccess($data);
    }

    public function store(ZoneRequest $request)
    {
        $zone = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(ZoneData::fromModel($zone), 'zone_created');
    }

    public function show(Zone $zone)
    {
        return respondSuccess(ZoneData::fromModel($zone->load('descriptions')));
    }

    public function update(ZoneRequest $request, Zone $zone)
    {
        $zone = $this->repo->saveFromCms($zone, $request->validated());

        return respondSuccess(ZoneData::fromModel($zone), 'zone_updated');
    }

    public function destroy(Zone $zone)
    {
        $this->repo->deleteByIds([$zone->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $zone = $this->repo->restoreById((int) $id);
        abort_if($zone === null, 404);

        return respondSuccess(ZoneData::fromModel($zone), 'zone_restored');
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
