<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\MenuData;
use App\Http\Requests\Cms\MenuRequest;
use App\Models\Entities\Menu;
use App\Repositories\Interfaces\MenuRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Menu API (REST) cho CMS — menu cha (title/position/theme), KHÔNG kèm
 * menu_value (xem MenuValueController riêng cho cây menu con).
 * Mirror CategoryController, nhưng KHÔNG có vòng đồng bộ *_descriptions vì
 * bảng `menu` không đa ngôn ngữ.
 */
class MenuController extends BaseCmsController
{
    protected string $permission = 'menu';

    public function __construct(
        private readonly MenuRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        // $into = PaginatedDataCollection bắt buộc — xem comment cùng chỗ ở
        // ProductController::index(), collect() không có $into trả về THẲNG
        // LengthAwarePaginator (shape phẳng của Laravel) chứ không phải
        // {data, meta:{total}} mà frontend cần.
        return MenuData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(MenuRequest $request)
    {
        $menu = $this->repo->saveFromCms(null, $request->validated());

        return response()->json(['data' => MenuData::from($menu)], 201);
    }

    public function show(Menu $menu)
    {
        return response()->json(['data' => MenuData::from($menu)]);
    }

    public function update(MenuRequest $request, Menu $menu)
    {
        $menu = $this->repo->saveFromCms($menu, $request->validated());

        return response()->json(['data' => MenuData::from($menu)]);
    }

    public function destroy(Menu $menu)
    {
        $this->repo->deleteByIds([$menu->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $menu = $this->repo->restoreById((int) $id);
        abort_if($menu === null, 404);

        return response()->json(['data' => MenuData::from($menu)]);
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
