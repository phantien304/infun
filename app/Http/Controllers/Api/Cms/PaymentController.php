<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\PaymentRepositoryInterface;

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

        return respondSuccess($data);
    }
}
