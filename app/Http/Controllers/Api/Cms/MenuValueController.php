<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\MenuValueData;
use App\Http\Requests\Cms\MenuValueRequest;
use App\Models\Entities\MenuValue;
use App\Repositories\Interfaces\MenuValueRepositoryInterface;
use Illuminate\Http\Request;

class MenuValueController extends BaseCmsController
{
    protected string $permission = 'menu-value';

    public function __construct(
        private readonly MenuValueRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        $menuId = (int) $request->input('menu_id');
        abort_if($menuId <= 0, 422, 'menu_id is required');

        $list = $this->repo->getTreeForCms($menuId);

        return respondSuccess($list->map(fn ($mv) => MenuValueData::fromModel($mv))->values());
    }

    public function show(MenuValue $menuValue)
    {
        $menuValue->load('descriptions');
        $type   = (string) $menuValue->type;
        $itemId = $menuValue->item_id !== null ? (int) $menuValue->item_id : null;
        $itemName   = $this->repo->resolveItemName($type, $itemId);
        $itemExists = $this->repo->resolveItemExists($type, $itemId);

        return respondSuccess(MenuValueData::fromModel($menuValue, $itemName, $itemExists), 'menu_value_show');
    }

    public function store(MenuValueRequest $request)
    {
        $mv = $this->repo->saveFromCms(null, $request->validated());
        $itemExists = $this->repo->resolveItemExists((string) $mv->type, $mv->item_id !== null ? (int) $mv->item_id : null);

        return respondCreated(MenuValueData::fromModel($mv, null, $itemExists), 'menu_value_created');
    }

    public function update(MenuValueRequest $request, MenuValue $menuValue)
    {
        $mv = $this->repo->saveFromCms($menuValue, $request->validated());
        $itemExists = $this->repo->resolveItemExists((string) $mv->type, $mv->item_id !== null ? (int) $mv->item_id : null);

        return respondSuccess(MenuValueData::fromModel($mv, null, $itemExists), 'menu_value_updated');
    }

    public function rename(Request $request, MenuValue $menuValue)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $title = $this->repo->renameTitle($menuValue->id, $data['title']);

        return respondSuccess(['title' => $title], 'menu_value_renamed');
    }

    public function destroy(MenuValue $menuValue)
    {
        $deleted = $this->repo->deleteWithDescendants($menuValue->id);

        return respondSuccess(['deleted' => $deleted], 'menu_value_deleted');
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

        return respondSuccess(['success' => true], 'menu_value_reordered');
    }

    public function importCategory($menuId)
    {
        $created = $this->repo->importCategoryTree((int) $menuId);

        return respondSuccess(['created' => $created], 'menu_value_imported');
    }

    /** DELETE /menu/{menuId}/values/categories — xoá mọi node type=category (+ con cháu). */
    public function deleteCategories($menuId)
    {
        $deleted = $this->repo->deleteCategoryTree((int) $menuId);

        return respondSuccess(['deleted' => $deleted], 'menu_value_categories_deleted');
    }
}
