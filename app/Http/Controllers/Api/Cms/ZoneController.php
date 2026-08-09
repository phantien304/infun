<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\ZoneRepositoryInterface;

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
