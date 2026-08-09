<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\CarrierRepositoryInterface;

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
