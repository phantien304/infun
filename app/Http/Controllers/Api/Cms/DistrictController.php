<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\DistrictRepositoryInterface;
use Illuminate\Http\Request;

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
