<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\OrdersStatusRepositoryInterface;

class OrderStatusController extends BaseCmsController
{
    protected string $permission = 'order-status';

    public function __construct(
        private readonly OrdersStatusRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->getAll()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        return respondSuccess($data);
    }
}
