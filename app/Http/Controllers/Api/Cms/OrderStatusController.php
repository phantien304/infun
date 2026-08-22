<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\OrderStatusData;
use App\Http\Requests\Cms\OrderStatusRequest;
use App\Repositories\Interfaces\OrdersStatusRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class OrderStatusController extends BaseCmsController
{
    protected string $permission = 'order-status';

    public function __construct(
        private readonly OrdersStatusRepositoryInterface $repo
    ) {
    }

    /**
     * 2 CHẾ ĐỘ trên CÙNG 1 route (tránh phải thêm route mới + đổi mọi nơi
     * đang gọi dropdown):
     *  - KHÔNG có query param nào (cách gọi hiện tại của order/index.jsx,
     *    order/form.jsx, order/view.jsx, setting — 4 chỗ) → giữ NGUYÊN hành
     *    vi cũ: mảng phẳng {id,name} theo locale hiện tại, không phân trang.
     *  - CÓ ít nhất 1 trong các param list chuẩn (page/per_page/keyword/
     *    deleted_at/sort/order) → coi là màn CMS list mới (FormSearch+Pager),
     *    trả PaginatedDataCollection như mọi entity khác.
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return OrderStatusData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->getAll()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        return respondSuccess($data);
    }

    public function store(OrderStatusRequest $request)
    {
        $id  = $this->repo->saveFromCms(null, $request->validated());
        $rows = $this->repo->getForCms($id);

        return respondCreated(OrderStatusData::fromRows($id, $rows), 'order_status_created');
    }

    public function show($id)
    {
        $rows = $this->repo->getForCms((int) $id);
        abort_if($rows->isEmpty(), 404);

        return respondSuccess(OrderStatusData::fromRows((int) $id, $rows));
    }

    public function update(OrderStatusRequest $request, $id)
    {
        $id   = (int) $id;
        $this->repo->saveFromCms($id, $request->validated());
        $rows = $this->repo->getForCms($id);
        abort_if($rows->isEmpty(), 404);

        return respondSuccess(OrderStatusData::fromRows($id, $rows), 'order_status_updated');
    }

    public function destroy($id)
    {
        $this->repo->deleteByIds([(int) $id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $rows = $this->repo->restoreById((int) $id);
        abort_if($rows->isEmpty(), 404);

        return respondSuccess(OrderStatusData::fromRows((int) $id, $rows), 'order_status_restored');
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
