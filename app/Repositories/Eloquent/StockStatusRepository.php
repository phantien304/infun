<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StockStatus;
use App\Models\Entities\StockStatusDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\StockStatusRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockStatusRepository extends QueryableRepository implements StockStatusRepositoryInterface
{
    public function model(): string
    {
        return StockStatus::class;
    }

    public function listWithDescription(): Collection
    {
        return StockStatus::with('description')->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'stock_status_description.name' : 'stock_status.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('stock_status_description', function ($join) use ($lang) {
                $join->on('stock_status_description.stock_status_id', '=', 'stock_status.id')
                    ->where('stock_status_description.language_code', '=', $lang);
            })
            ->select('stock_status.*', 'stock_status_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('stock_status_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?StockStatus
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?StockStatus $status, array $data): StockStatus
    {
        return DB::transaction(function () use ($status, $data) {
            $status ??= new StockStatus();
            $status->save();

            foreach ((array) ($data['stock_status_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = StockStatusDescription::where('stock_status_id', $status->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['name'])) {
                    $desc ??= new StockStatusDescription();
                    $desc->stock_status_id = $status->id;
                    $desc->language_code   = $code;
                    $desc->name            = $item['name'];
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $status->load('descriptions');
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

    public function restoreById(int $id): ?StockStatus
    {
        $status = $this->resetModel()->withTrashed()->find($id);
        $status?->restore();

        return $status?->load('descriptions');
    }
}
