<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\BannerData;
use App\Http\Requests\Cms\BannerRequest;
use App\Models\Entities\Banner;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class BannerController extends BaseCmsController
{
    protected string $permission = 'banner';

    public function __construct(
        private readonly BannerRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return BannerData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(BannerRequest $request)
    {
        $banner = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(BannerData::fromModel($banner), 'banner_created');
    }

    public function show(Banner $banner)
    {
        $banner->load(['descriptions', 'bannerValues.descriptions']);

        return respondSuccess(BannerData::fromModel($banner));
    }

    public function update(BannerRequest $request, Banner $banner)
    {
        $banner = $this->repo->saveFromCms($banner, $request->validated());

        return respondSuccess(BannerData::fromModel($banner), 'banner_updated');
    }

    public function destroy(Banner $banner)
    {
        $this->repo->deleteByIds([$banner->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $banner = $this->repo->restoreById((int) $id);
        abort_if($banner === null, 404);

        return respondSuccess(BannerData::fromModel($banner), 'banner_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->repo->deleteByIds($data['ids'])
            : $this->repo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
