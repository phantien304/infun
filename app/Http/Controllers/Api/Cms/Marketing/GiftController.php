<?php

namespace App\Http\Controllers\Api\Cms\Marketing;

use App\Data\Cms\GiftData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\GiftRequest;
use App\Models\Entities\Gift;
use App\Repositories\Interfaces\GiftRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS chương trình quà tặng — MÀN MỚI, mt219 không có.
 *
 * Quản lý bộ ba `gift` / `gift_item` / `gift_trigger_product`; repository lo
 * ràng buộc FK RESTRICT với `order_gift` (không cho xoá món quà đã có đơn
 * nhận).
 */
class GiftController extends BaseCmsController
{
    protected string $permission = 'gift';

    public function __construct(
        private readonly GiftRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return GiftData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(GiftRequest $request)
    {
        $gift = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(GiftData::fromModel($gift), 'gift_created');
    }

    public function show($id)
    {
        $gift = $this->repo->getForCms((int) $id);
        abort_if($gift === null, 404);

        return respondSuccess(GiftData::fromModel($gift));
    }

    public function update(GiftRequest $request, Gift $gift)
    {
        $gift = $this->repo->saveFromCms($gift, $request->validated());

        return respondSuccess(GiftData::fromModel($gift), 'gift_updated');
    }

    public function destroy(Gift $gift)
    {
        $this->repo->deleteByIds([$gift->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $gift = $this->repo->restoreById((int) $id);
        abort_if($gift === null, 404);

        return respondSuccess(GiftData::fromModel($gift), 'gift_restored');
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
