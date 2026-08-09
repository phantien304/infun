<?php

namespace App\Http\Controllers\Api\Cms\System;

use App\Data\Cms\UserGroupData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\UserGroupRequest;
use App\Models\Entities\UserGroup;
use App\Repositories\Interfaces\UserGroupRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class UserGroupController extends BaseCmsController
{
    protected string $permission = 'user-group';

    public function __construct(
        private readonly UserGroupRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return UserGroupData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(UserGroupRequest $request)
    {
        $userGroup = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(UserGroupData::fromModel($userGroup), 'user_group_created');
    }

    public function show(UserGroup $userGroup)
    {
        $userGroup->load('descriptions');

        return respondSuccess(UserGroupData::fromModel($userGroup), 'user_group_shown');
    }

    public function update(UserGroupRequest $request, UserGroup $userGroup)
    {
        $userGroup = $this->repo->saveFromCms($userGroup, $request->validated());

        return respondSuccess(UserGroupData::fromModel($userGroup), 'user_group_updated');
    }

    public function destroy(UserGroup $userGroup)
    {
        $this->repo->deleteByIds([$userGroup->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $userGroup = $this->repo->restoreById((int) $id);
        abort_if($userGroup === null, 404);

        return respondSuccess(UserGroupData::fromModel($userGroup), 'user_group_restored');
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
