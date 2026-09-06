<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Payment;
use App\Models\Entities\PaymentDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentRepository extends QueryableRepository implements PaymentRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Payment::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.payments'),
            fn () => $this->resetModel()
                ->with('description')
                ->orderBy('sort_order', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
        );
    }

    public function findByCode(string $code): ?Payment
    {
        return $this->rememberCache(
            setting('cache.payments') . $code,
            fn () => $this->resetModel()
                ->with('description')
                ->where('code', $code)
                ->first()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.payments'));
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['code', 'sort_order'], true)
            ? $request->input('sort')
            : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', 1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()->with('description');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', '%' . $keyword . '%')
                    ->orWhereHas('description', function ($dq) use ($keyword) {
                        $dq->where('name', 'like', '%' . $keyword . '%');
                    });
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Payment
    {
        return $this->resetModel()->with('description')->withTrashed()->find($id);
    }

    public function saveFromCms(?Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment ??= new Payment();
            $payment->code       = $data['code'];
            $payment->image      = $data['image'] ?? null;
            $payment->sort_order = (int) ($data['sort_order'] ?? 0);
            $payment->save();

            $languageCode = app()->getLocale();
            $desc = PaymentDescription::where('payment_id', $payment->id)
                ->where('language_code', $languageCode)
                ->first();
            $desc ??= new PaymentDescription();
            $desc->payment_id     = $payment->id;
            $desc->language_code  = $languageCode;
            $desc->name           = $data['name'];
            $desc->save();

            $this->flushCache();

            return $payment->load('description');
        });
    }

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) — nhất quán với
     * CarrierRepository::deleteByIds, đảm bảo observer/soft-delete hook
     * (nếu có) chạy đúng cho từng row.
     */
    public function deleteByIds(array $ids): int
    {
        $rows = $this->resetModel()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->delete();
        }
        $this->flushCache();

        return $rows->count();
    }

    public function restoreByIds(array $ids): int
    {
        $rows = $this->resetModel()->withTrashed()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->restore();
        }
        $this->flushCache();

        return $rows->count();
    }

    public function restoreById(int $id): ?Payment
    {
        $payment = $this->resetModel()->withTrashed()->find($id);
        $payment?->restore();
        $this->flushCache();

        return $payment;
    }
}
