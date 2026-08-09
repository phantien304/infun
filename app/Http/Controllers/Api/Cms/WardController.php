<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\WardRepositoryInterface;
use Illuminate\Http\Request;

class WardController extends BaseCmsController
{
    protected string $permission = 'ward';

    public function __construct(
        private readonly WardRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        $districtId = (int) $request->input('district_id');
        abort_if($districtId <= 0, 422, 'district_id is required');

        $data = $this->repo->listByDistrict($districtId)
            ->map(fn ($w) => ['id' => $w->id, 'name' => $w->description?->name ?? ''])
            ->values();

        return respondSuccess($data);
    }
}
