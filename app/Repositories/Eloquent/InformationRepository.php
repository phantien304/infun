<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Information;
use App\Models\Entities\InformationDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\InformationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformationRepository extends QueryableRepository implements InformationRepositoryInterface
{
    public function model(): string
    {
        return Information::class;
    }

    public function getDetail($id): ?Information
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()
            ->with('description')
            ->find($id);
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
        $sort    = $request->input('sort') === 'title' ? 'information_description.title' : 'information.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('information_description', function ($join) use ($lang) {
                $join->on('information_description.information_id', '=', 'information.id')
                    ->where('information_description.language_code', '=', $lang);
            })
            ->select('information.*', 'information_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('information_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Information
    {
        return $this->resetModel()->withTrashed()->with(['descriptions'])->find($id);
    }

    public function saveFromCms(?Information $information, array $data): Information
    {
        return DB::transaction(function () use ($information, $data) {
            $information ??= new Information();
            $information->banner_id  = $this->nullableInt($data['banner_id'] ?? null);
            $information->sort_order = (int) ($data['sort_order'] ?? 0);
            $information->save();

            foreach ((array) ($data['information_descriptions'] ?? []) as $item) {
                $this->saveInformationDescription($information->id, $item);
            }

            return $information->load(['descriptions']);
        });
    }

    protected function saveInformationDescription(int $informationId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = InformationDescription::where('information_id', $informationId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['title'])) {
            $desc ??= new InformationDescription();
            $desc->information_id   = $informationId;
            $desc->language_code    = $code;
            $desc->title            = $item['title'];
            $desc->description      = $item['description'] ?? null;
            $desc->content          = $item['content'] ?? null;
            $desc->meta_title       = $item['meta_title'] ?? null;
            $desc->meta_description = $item['meta_description'] ?? null;
            $desc->save();
        } elseif ($desc) {
            $desc->delete();
        }
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Information
    {
        $information = $this->resetModel()->withTrashed()->find($id);
        $information?->restore();

        return $information?->load(['descriptions']);
    }
}
