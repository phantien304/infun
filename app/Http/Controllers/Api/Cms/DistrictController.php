<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\DistrictRepositoryInterface;
use Illuminate\Http\Request;

/**
 * District (quận/huyện) — CHỈ read, phụ thuộc zone_id (dropdown địa chỉ ở
 * order/form.jsx, order/view.jsx — cascading theo Zone đã chọn). CRUD
 * district đầy đủ là màn hình riêng, chưa convert. Permission slug
 * 'district' khớp sp_permissions có sẵn (list-district).
 */
class DistrictController extends BaseCmsController
{
    protected string $permission = 'district';

    public function __construct(
        private readonly DistrictRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        $zoneId = (int) $request->input('zone_id');
        abort_if($zoneId <= 0, 422, 'zone_id is required');

        $data = $this->repo->listByZone($zoneId)
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->description?->name ?? ''])
            ->values();

        return respondSuccess($data);
    }
}
