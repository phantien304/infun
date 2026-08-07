<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\MenuValueData;
use App\Http\Requests\Cms\MenuValueRequest;
use App\Models\Entities\MenuValue;
use App\Repositories\Interfaces\MenuValueRepositoryInterface;
use Illuminate\Http\Request;

/**
 * MenuValue API (REST + action riêng) cho CMS — cây menu con của 1 Menu.
 * KHÔNG dùng Route::cmsApiResource (không có restore/bulk — menu_value
 * không soft-delete) + có 3 action ngoài REST chuẩn (reorder/import/xoá
 * category) khai riêng ở routes/rcms.php.
 *
 * Permission slug 'menu-value' (sp_permissions có sẵn list/detail/create/
 * edit/del-menu-value — TÁCH RIÊNG với 'menu' của MenuController).
 */
class MenuValueController extends BaseCmsController
{
    protected string $permission = 'menu-value';

    public function __construct(
        private readonly MenuValueRepositoryInterface $repo
    ) {
    }

    /** GET /menu-value?menu_id=X — cây phẳng (FE tự dựng theo parent_id). */
    public function index(Request $request)
    {
        $menuId = (int) $request->input('menu_id');
        abort_if($menuId <= 0, 422, 'menu_id is required');

        $list = $this->repo->getTreeForCms($menuId);

        return response()->json([
            'data' => $list->map(fn ($mv) => MenuValueData::fromModel($mv))->values(),
        ]);
    }

    public function show(MenuValue $menuValue)
    {
        $menuValue->load('descriptions');
        $type   = (string) $menuValue->type;
        $itemId = $menuValue->item_id !== null ? (int) $menuValue->item_id : null;
        $itemName   = $this->repo->resolveItemName($type, $itemId);
        $itemExists = $this->repo->resolveItemExists($type, $itemId);

        return response()->json(['data' => MenuValueData::fromModel($menuValue, $itemName, $itemExists)]);
    }

    public function store(MenuValueRequest $request)
    {
        $mv = $this->repo->saveFromCms(null, $request->validated());
        $itemExists = $this->repo->resolveItemExists((string) $mv->type, $mv->item_id !== null ? (int) $mv->item_id : null);

        return response()->json(['data' => MenuValueData::fromModel($mv, null, $itemExists)], 201);
    }

    public function update(MenuValueRequest $request, MenuValue $menuValue)
    {
        $mv = $this->repo->saveFromCms($menuValue, $request->validated());
        $itemExists = $this->repo->resolveItemExists((string) $mv->type, $mv->item_id !== null ? (int) $mv->item_id : null);

        return response()->json(['data' => MenuValueData::fromModel($mv, null, $itemExists)]);
    }

    /**
     * PATCH /menu-value/{id}/rename — "sửa tên tại chỗ" ngay trên cây, không
     * mở form đầy đủ. Chỉ 1 field title (locale admin đang xem — xem docblock
     * repo), KHÔNG dùng MenuValueRequest (field đó require cả type/descriptions
     * cho store/update đầy đủ, quá nặng cho 1 lần đổi tên nhanh).
     */
    public function rename(Request $request, MenuValue $menuValue)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $title = $this->repo->renameTitle($menuValue->id, $data['title']);

        return response()->json(['title' => $title]);
    }

    /** Xoá 1 node + toàn bộ node con cháu (tránh mồ côi parent_id). */
    public function destroy(MenuValue $menuValue)
    {
        $deleted = $this->repo->deleteWithDescendants($menuValue->id);

        return response()->json(['deleted' => $deleted]);
    }

    /** POST /menu/{menuId}/values/reorder — sau khi kéo-thả cây bên FE. */
    public function reorder(Request $request, $menuId)
    {
        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.id'         => 'required|integer',
            'items.*.parent_id'  => 'required|integer',
            'items.*.position'   => 'required|integer',
        ]);

        $this->repo->reorder((int) $menuId, $data['items']);

        return response()->json(['success' => true]);
    }

    /** POST /menu/{menuId}/values/import-category — sinh cây menu từ Category. */
    public function importCategory($menuId)
    {
        $created = $this->repo->importCategoryTree((int) $menuId);

        return response()->json(['created' => $created]);
    }

    /** DELETE /menu/{menuId}/values/categories — xoá mọi node type=category (+ con cháu). */
    public function deleteCategories($menuId)
    {
        $deleted = $this->repo->deleteCategoryTree((int) $menuId);

        return response()->json(['deleted' => $deleted]);
    }
}
