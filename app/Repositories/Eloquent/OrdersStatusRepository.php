<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\OrdersStatus;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OrdersStatusRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrdersStatusRepository extends QueryableRepository implements OrdersStatusRepositoryInterface
{
    public function model(): string
    {
        return OrdersStatus::class;
    }

    public function getAll(): Collection
    {
        return $this->resetModel()
            ->where('language_code', app()->getLocale())
            ->orderBy('id')
            ->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $sort        = $request->input('sort') === 'name' ? 'name' : 'id';
        $order       = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted     = (int) $request->input('deleted_at', 1);
        $keyword     = trim((string) $request->input('keyword', ''));
        $perPage     = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()->where('language_code', $defaultLang);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): Collection
    {
        return $this->resetModel()->withTrashed()
            ->where('id', $id)
            ->orderBy('language_code')
            ->get();
    }

    public function saveFromCms(?int $id, array $data): int
    {
        return DB::transaction(function () use ($id, $data) {
            $descriptions = $data['descriptions'] ?? [];

            if ($id === null) {
                $firstIndex = null;
                foreach ($descriptions as $i => $item) {
                    if (! empty($item['name'])) {
                        $firstIndex = $i;
                        break;
                    }
                }
                // FormRequest đã bắt buộc name ở ngôn ngữ mặc định nên luôn có
                // ít nhất 1 dòng non-blank — $firstIndex !== null được đảm bảo.
                $first = $descriptions[$firstIndex];
                unset($descriptions[$firstIndex]);

                $created = OrdersStatus::create([
                    'language_code' => $first['language_code'],
                    'name'          => $first['name'],
                ]);
                $id = (int) $created->id;
            }

            foreach ($descriptions as $item) {
                $this->saveDescriptionRow($id, $item);
            }

            return $id;
        });
    }

    /**
     * Upsert 1 dòng ngôn ngữ của status $id. `id` KHÔNG có trong $fillable
     * (chỉ language_code/name/deleted_at) nên set trực tiếp qua property
     * (bypass mass-assignment) khi tạo dòng ngôn ngữ mới cho 1 id đã có sẵn.
     */
    protected function saveDescriptionRow(int $id, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $row = $this->resetModel()->withTrashed()
            ->where('id', $id)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            if ($row) {
                $row->name = $item['name'];
                if ($row->trashed()) {
                    $row->restore();
                }
                $row->save();
            } else {
                $row = new OrdersStatus();
                $row->id            = $id;
                $row->language_code = $code;
                $row->name          = $item['name'];
                $row->save();
            }
        } elseif ($row && ! $row->trashed()) {
            $row->delete();
        }
    }

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) để cascade
     * ($destroyRelations = ordersStatusCarrierOrders) chạy đúng qua event
     * `deleting` — xem lý do chi tiết ở interface.
     */
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

    public function restoreById(int $id): Collection
    {
        $rows = $this->resetModel()->withTrashed()->where('id', $id)->get();
        foreach ($rows as $row) {
            $row->restore();
        }

        return $rows;
    }
}
