<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\WardData;
use App\Http\Requests\Cms\WardRequest;
use App\Models\Entities\Ward;
use App\Repositories\Interfaces\WardRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class WardController extends BaseCmsController
{
    protected string $permission = 'ward';

    public function __construct(
        private readonly WardRepositoryInterface $repo
    ) {
    }

    /**
     * 2 chế độ trên CÙNG 1 route:
     *  - CÓ district_id nhưng KHÔNG param list chuẩn → dropdown cũ cho
     *    checkout (giữ nguyên hành vi cũ, xem WardRepository::listByDistrict).
     *  - CÓ ít nhất 1 param list chuẩn → màn CMS list mới (FormSearch+Pager),
     *    district_id lúc này là filter tuỳ chọn.
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return WardData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $districtId = (int) $request->input('district_id');
        abort_if($districtId <= 0, 422, 'district_id is required');

        $data = $this->repo->listByDistrict($districtId)
            ->map(fn ($w) => ['id' => $w->id, 'name' => $w->description?->name ?? ''])
            ->values();

        return respondSuccess($data);
    }

    public function store(WardRequest $request)
    {
        $ward = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(WardData::fromModel($ward), 'ward_created');
    }

    public function show(Ward $ward)
    {
        return respondSuccess(WardData::fromModel($ward->load('descriptions')));
    }

    public function update(WardRequest $request, Ward $ward)
    {
        $ward = $this->repo->saveFromCms($ward, $request->validated());

        return respondSuccess(WardData::fromModel($ward), 'ward_updated');
    }

    public function destroy(Ward $ward)
    {
        $this->repo->deleteByIds([$ward->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $ward = $this->repo->restoreById((int) $id);
        abort_if($ward === null, 404);

        return respondSuccess(WardData::fromModel($ward), 'ward_restored');
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
