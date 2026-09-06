<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\DistrictData;
use App\Http\Requests\Cms\DistrictRequest;
use App\Models\Entities\District;
use App\Repositories\Interfaces\DistrictRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class DistrictController extends BaseCmsController
{
    protected string $permission = 'district';

    public function __construct(
        private readonly DistrictRepositoryInterface $repo
    ) {
    }

    /**
     * 2 chế độ trên CÙNG 1 route:
     *  - CÓ zone_id nhưng KHÔNG param list chuẩn → dropdown cũ cho checkout
     *    (giữ nguyên hành vi cũ, xem DistrictRepository::listByZone).
     *  - CÓ ít nhất 1 param list chuẩn → màn CMS list mới (FormSearch+Pager),
     *    zone_id lúc này là filter tuỳ chọn (không bắt buộc).
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return DistrictData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $zoneId = (int) $request->input('zone_id');
        abort_if($zoneId <= 0, 422, 'zone_id is required');

        $data = $this->repo->listByZone($zoneId)
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->description?->name ?? ''])
            ->values();

        return respondSuccess($data);
    }

    public function store(DistrictRequest $request)
    {
        $district = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(DistrictData::fromModel($district), 'district_created');
    }

    public function show(District $district)
    {
        return respondSuccess(DistrictData::fromModel($district->load('descriptions')));
    }

    public function update(DistrictRequest $request, District $district)
    {
        $district = $this->repo->saveFromCms($district, $request->validated());

        return respondSuccess(DistrictData::fromModel($district), 'district_updated');
    }

    public function destroy(District $district)
    {
        $this->repo->deleteByIds([$district->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $district = $this->repo->restoreById((int) $id);
        abort_if($district === null, 404);

        return respondSuccess(DistrictData::fromModel($district), 'district_restored');
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
