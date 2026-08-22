<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Orders;
use App\Models\Entities\Voucher;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherRepository extends QueryableRepository implements VoucherRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Voucher::class;
    }

    public function findByCode(string $code): ?Voucher
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        return $this->resetModel()
            ->newQuery()
            ->where('code', $code)
            ->with(['voucherTheme'])
            ->first();
    }

    public function findByCodes(array $codes): Collection
    {
        $codes = array_values(array_filter(array_map('trim', $codes), fn ($c) => $c !== ''));
        if (empty($codes)) {
            return collect();
        }
        return $this->resetModel()
            ->newQuery()
            ->whereIn('code', $codes)
            ->with(['voucherTheme'])
            ->get()
            ->keyBy('code');
    }

    public function listForEmail(string $email): Collection
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return collect();
        }
        return $this->resetModel()
            ->newQuery()
            ->forEmail($email)
            ->with(['voucherTheme'])
            ->orderByDesc('id')
            ->get();
    }

    public function incrementRedeemed(int $voucherId, float $amount): int
    {
        return DB::table('voucher')
            ->where('id', $voucherId)
            ->whereRaw('redeemed_balance + ? <= amount', [$amount])
            ->whereNull('deleted_at')
            ->increment('redeemed_balance', $amount);
    }

    public function decrementRedeemed(int $voucherId, float $amount): void
    {
        DB::table('voucher')
            ->where('id', $voucherId)
            ->where('redeemed_balance', '>=', $amount)
            ->whereNull('deleted_at')
            ->decrement('redeemed_balance', $amount);
    }

    public function markFullyUsed(array $voucherIds, int $activeStatus, int $fullyUsedStatus): void
    {
        if (empty($voucherIds)) {
            return;
        }
        DB::table('voucher')
            ->whereIn('id', $voucherIds)
            ->where('status', $activeStatus)
            ->whereColumn('redeemed_balance', '>=', 'amount')
            ->whereNull('deleted_at')
            ->update(['status' => $fullyUsedStatus]);
    }

    public function reactivateVouchers(array $voucherIds, int $fullyUsedStatus, int $activeStatus): void
    {
        if (empty($voucherIds)) {
            return;
        }
        DB::table('voucher')
            ->whereIn('id', $voucherIds)
            ->where('status', $fullyUsedStatus)
            ->whereColumn('redeemed_balance', '<', 'amount')
            ->whereNull('deleted_at')
            ->update(['status' => $activeStatus]);
    }

    public function createVoucher(array $data): Voucher
    {
        return Voucher::create($data);
    }

    public function revokeUnused(array $voucherIds, int $activeStatus, int $revokedStatus): int
    {
        if (empty($voucherIds)) {
            return 0;
        }

        return DB::table('voucher')
            ->whereIn('id', $voucherIds)
            ->where('status', $activeStatus)
            ->where('redeemed_balance', 0)
            ->whereNull('deleted_at')
            ->update(['status' => $revokedStatus]);
    }

    public function reactivateRevoked(array $voucherIds, int $revokedStatus, int $activeStatus): int
    {
        if (empty($voucherIds)) {
            return 0;
        }

        return DB::table('voucher')
            ->whereIn('id', $voucherIds)
            ->where('status', $revokedStatus)
            ->where('redeemed_balance', 0)
            ->whereNull('deleted_at')
            ->update(['status' => $activeStatus]);
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('voucher.cache.tag_root')]);
    }

    // === Legacy API ===

    public function resolveVoucher(?string $code): array
    {
        if (! filled($code)) {
            return [];
        }

        $voucher = $this->resetModel()
            ->where('code', $code)
            ->with(['voucherTheme.voucherThemeDescription' => fn ($q) => $q->where('language_code', app()->getLocale())])
            ->first();

        if (! $voucher) {
            return [];
        }

        if ($voucher->order_id) {
            $completeStatuses = (array) getConfigDb('order_complete_status_all', []);

            $orderOk = Orders::where('id', $voucher->order_id)
                ->whereIn('order_status_id', array_map('intval', $completeStatuses))
                ->exists();
            if (! $orderOk) {
                return [];
            }
        }

        $usedDelta = (int) DB::table('voucher_history')
            ->where('voucher_id', $voucher->id)
            ->sum('amount');
        $remaining = (int) $voucher->amount + $usedDelta;

        if ($remaining <= 0) {
            return [];
        }

        return [
            'id'               => $voucher->id,
            'code'             => $voucher->code,
            'from_name'        => $voucher->from_name,
            'from_email'       => $voucher->from_email,
            'to_name'          => $voucher->to_name,
            'to_email'         => $voucher->to_email,
            'voucher_theme_id' => $voucher->voucher_theme_id,
            'theme'            => $voucher->voucherTheme?->voucherThemeDescription?->name,
            'message'          => $voucher->message,
            'image'            => $voucher->voucherTheme?->image,
            'amount'           => $remaining,
            'created_at'       => $voucher->created_at,
        ];
    }

    // ===================== CMS (admin) =====================

    /**
     * Danh sách voucher cho CMS. Lọc thêm theo `status` (1 active / 2 expired
     * / 3 fully_used / 4 revoked) và `to_email` — 2 câu hỏi hay gặp nhất khi
     * CSKH tra thẻ quà tặng của một khách.
     */
    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'code', 'amount', 'status', 'date_expire', 'created_at'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted  = (int) $request->input('deleted_at', -1);
        $keyword  = trim((string) $request->input('keyword', ''));
        $perPage  = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery()->with(['voucherTheme.voucherThemeDescriptions']);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', '%' . $keyword . '%')
                    ->orWhere('to_email', 'like', '%' . $keyword . '%')
                    ->orWhere('to_name', 'like', '%' . $keyword . '%')
                    ->orWhere('from_email', 'like', '%' . $keyword . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('to_email')) {
            $query->where('to_email', trim((string) $request->input('to_email')));
        }

        return $query->orderBy($sort, $order)->orderBy('id', 'desc')->paginate($perPage);
    }

    public function getForCms(int $id): ?Voucher
    {
        return $this->resetModel()
            ->withTrashed()
            ->with([
                'voucherTheme.voucherThemeDescriptions',
                'voucherHistories' => fn ($q) => $q->orderByDesc('id')->limit(200),
            ])
            ->find($id);
    }

    /**
     * `redeemed_balance` và `sent_at` KHÔNG lấy từ $data: một cái là sổ tiền
     * đã tiêu (incrementRedeemed/decrementRedeemed ghi theo giao dịch), một
     * cái do job gửi mail đóng dấu. Sửa tay ở CMS = số dư sai với
     * voucher_history.
     */
    public function saveFromCms(?Voucher $voucher, array $data): Voucher
    {
        unset($data['redeemed_balance'], $data['sent_at']);

        $voucher ??= new Voucher();
        $voucher->fill($data);
        $voucher->save();
        $this->flushCache();

        return $voucher->load(['voucherTheme.voucherThemeDescriptions']);
    }

    /**
     * Xoá dấu đã gửi để GỬI LẠI mail voucher.
     *
     * VoucherRewardSendEmailJob tự "claim" bằng
     * `whereNull('sent_at')->update(...)` — chống gửi trùng khi job retry.
     * Nên muốn CMS gửi lại thật thì phải trả `sent_at` về NULL trước, chứ
     * không phải dispatch thêm một lần nữa (job sẽ lặng lẽ return).
     */
    public function clearSent(array $voucherIds): int
    {
        if (empty($voucherIds)) {
            return 0;
        }

        return DB::table('voucher')
            ->whereIn('id', $voucherIds)
            ->whereNull('deleted_at')
            ->update(['sent_at' => null]);
    }

    public function deleteByIds(array $ids): int
    {
        $affected = $this->resetModel()->whereIn('id', $ids)->delete();
        $this->flushCache();

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        $affected = $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
        $this->flushCache();

        return $affected;
    }

    public function restoreById(int $id): ?Voucher
    {
        $voucher = $this->resetModel()->withTrashed()->find($id);
        $voucher?->restore();
        $this->flushCache();

        return $voucher?->load(['voucherTheme.voucherThemeDescriptions']);
    }
}
