<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\MenuData;
use App\Http\Requests\Cms\MenuRequest;
use App\Models\Entities\Menu;
use App\Repositories\Interfaces\MenuRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class MenuController extends BaseCmsController
{
    protected string $permission = 'menu';

    public function __construct(
        private readonly MenuRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return MenuData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(MenuRequest $request)
    {
        $menu = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(MenuData::from($menu), 'menu_created');
    }

    public function show(Menu $menu)
    {
        return respondSuccess(MenuData::from($menu));
    }

    public function update(MenuRequest $request, Menu $menu)
    {
        $menu = $this->repo->saveFromCms($menu, $request->validated());

        return respondSuccess(MenuData::from($menu), 'menu_updated');
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

        return respondSuccess(MenuData::from($menu), 'menu_restored');
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
