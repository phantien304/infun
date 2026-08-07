<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\PaymentRepositoryInterface;

/**
 * Payment — CHỈ read (dropdown "Phương thức thanh toán" ở order/form.jsx tab
 * Confirm). CRUD payment đầy đủ là màn hình riêng, chưa convert (router
 * 'payment' phía infuncms đang placeholder). Permission slug 'payment' khớp
 * sp_permissions có sẵn (list-payment).
 */
class PaymentController extends BaseCmsController
{
    protected string $permission = 'payment';

    public function __construct(
        private readonly PaymentRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->listAllCached()
            ->map(fn ($p) => [
                'id'   => $p->id,
                'code' => $p->code,
                'name' => $p->description?->name ?? '',
            ])
            ->values();

        return response()->json(['data' => $data]);
    }
}
