<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserGroup;
use App\Models\Entities\UserGroupDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserGroupRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserGroupRepository extends QueryableRepository implements UserGroupRepositoryInterface
{
    public function model(): string
    {
        return UserGroup::class;
    }

    public function listWithDescription(): Collection
    {
        return $this->resetModel()->with('description')->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'user_group_description.name' : 'user_group.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('user_group_description', function ($join) use ($lang) {
                $join->on('user_group_description.user_group_id', '=', 'user_group.id')
                    ->where('user_group_description.language_code', '=', $lang);
            })
            ->select('user_group.*', 'user_group_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('user_group_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?UserGroup
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?UserGroup $userGroup, array $data): UserGroup
    {
        return DB::transaction(function () use ($userGroup, $data) {
            $userGroup ??= new UserGroup();
            $userGroup->approval   = (int) ($data['approval'] ?? 0);
            $userGroup->sort_order = (int) ($data['sort_order'] ?? 0);
            $userGroup->save();

            foreach ((array) ($data['user_group_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = UserGroupDescription::where('user_group_id', $userGroup->id)
                    ->where('language_code', $code)->first();

                if (! empty($item['name'])) {
                    $desc ??= new UserGroupDescription();
                    $desc->user_group_id = $userGroup->id;
                    $desc->language_code = $code;
                    $desc->name          = $item['name'];
                    $desc->description   = $item['description'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $userGroup->load('descriptions');
        });
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?UserGroup
    {
        $userGroup = $this->resetModel()->withTrashed()->find($id);
        $userGroup?->restore();

        return $userGroup?->load('descriptions');
    }
}
