<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\ZoneRepositoryInterface;

/**
 * Zone (tỉnh/thành) — CHỈ read (dropdown địa chỉ ở order/form.jsx,
 * order/view.jsx). ZoneRepository::baseQuery() đã tự lọc theo country mặc
 * định — hệ thống hiện không cho chọn country (xem OrderAdminWriteService).
 * CRUD zone đầy đủ là màn hình riêng, chưa convert (router 'zone' phía
 * infuncms đang placeholder). Permission slug 'zone' khớp sp_permissions có
 * sẵn (list-zone).
 */
class ZoneController extends BaseCmsController
{
    protected string $permission = 'zone';

    public function __construct(
        private readonly ZoneRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->listAllCached()
            ->map(fn ($z) => ['id' => $z->id, 'name' => $z->description?->name ?? ''])
            ->values();

        return respondSuccess($data);
    }
}
