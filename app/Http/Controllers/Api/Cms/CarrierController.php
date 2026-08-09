<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\CarrierRepositoryInterface;

/**
 * Carrier — CHỈ read (dropdown "Hãng vận chuyển" ở order/form.jsx tab Confirm).
 * CRUD carrier đầy đủ là màn hình riêng, chưa convert (router 'carrier' phía
 * infuncms đang placeholder). Permission slug 'carrier' khớp sp_permissions
 * có sẵn (list-carrier).
 */
class CarrierController extends BaseCmsController
{
    protected string $permission = 'carrier';

    public function __construct(
        private readonly CarrierRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->listAllCached()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'code' => $c->code,
                'name' => $c->name,
            ])
            ->values();

        return respondSuccess($data);
    }
}
