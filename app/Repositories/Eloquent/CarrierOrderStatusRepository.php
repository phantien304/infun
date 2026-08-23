<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\CarrierOrderStatus;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\CarrierOrderStatusRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CarrierOrderStatusRepository extends QueryableRepository implements CarrierOrderStatusRepositoryInterface
{
    public function model(): string
    {
        return CarrierOrderStatus::class;
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['name', 'code'], true)
            ? $request->input('sort')
            : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', 1);
        $keyword = trim((string) $request->input('keyword', ''));
        $carrierId = $request->input('carrier_id');
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()->with('carrier');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($carrierId) {
            $query->where('carrier_id', (int) $carrierId);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?CarrierOrderStatus
    {
        $status = $this->resetModel()->withTrashed()->with('carrier')->find($id);
        if ($status === null) {
            return null;
        }

        // `description` không phải cột thật trên carrier_order_status — gán
        // sau save() (KHÔNG qua fillable/mass-assign) để CarrierOrderStatusData
        // đọc được, không đụng tới model CarrierOrderStatusDescription (PK
        // khai sai kiểu mảng — xem interface).
        $status->setAttribute('description', DB::table('carrier_order_status_description')
            ->where('carrier_order_status_id', $id)
            ->value('description'));

        return $status;
    }

    public function saveFromCms(?CarrierOrderStatus $status, array $data): CarrierOrderStatus
    {
        return DB::transaction(function () use ($status, $data) {
            $status ??= new CarrierOrderStatus();
            $status->carrier_id = (int) $data['carrier_id'];
            $status->code       = $data['code'];
            $status->name       = $data['name'];
            $status->save();

            $description = trim((string) ($data['description'] ?? ''));
            if ($description !== '') {
                DB::table('carrier_order_status_description')->updateOrInsert(
                    ['carrier_order_status_id' => $status->id],
                    [
                        'language_code' => getConfigDb('config_language_admin') ?: 'vi',
                        'description'   => $description,
                        'updated_at'    => now(),
                        'created_at'    => now(),
                    ]
                );
            } else {
                DB::table('carrier_order_status_description')
                    ->where('carrier_order_status_id', $status->id)
                    ->delete();
            }

            return $status->load('carrier');
        });
    }

    public function deleteByIds(array $ids): int
    {
        $rows = $this->resetModel()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->delete();
        }

        return $rows->count();
    }

    public function restoreByIds(array $ids): int
    {
        $rows = $this->resetModel()->withTrashed()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->restore();
        }

        return $rows->count();
    }

    public function restoreById(int $id): ?CarrierOrderStatus
    {
        $status = $this->resetModel()->withTrashed()->find($id);
        $status?->restore();

        return $status;
    }
}
