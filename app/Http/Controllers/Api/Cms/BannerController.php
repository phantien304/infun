<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\BannerData;
use App\Http\Requests\Cms\BannerRequest;
use App\Models\Entities\Banner;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Banner API (REST) cho CMS — mirror CategoryController.
 * Controller chỉ điều phối: request → repository → DTO, không chứa code
 * tương tác DB (nằm ở BannerRepository — house style).
 *
 * Khác Category: banner có thêm 1 tầng con banner_values[] (mỗi dòng là 1
 * ảnh HOẶC 1 video — field media_type/video_provider/video_url), mỗi value
 * lại có banner_value_descriptions[] theo ngôn ngữ. saveFromCms xử lý cả
 * 2 tầng trong 1 transaction.
 */
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

        return response()->json(['data' => BannerData::fromModel($banner)], 201);
    }

    public function show(Banner $banner)
    {
        $banner->load(['descriptions', 'bannerValues.descriptions']);

        return response()->json(['data' => BannerData::fromModel($banner)]);
    }

    public function update(BannerRequest $request, Banner $banner)
    {
        $banner = $this->repo->saveFromCms($banner, $request->validated());

        return response()->json(['data' => BannerData::fromModel($banner)]);
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

        return response()->json(['data' => BannerData::fromModel($banner)]);
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

        return response()->json(['affected' => $affected]);
    }
}
