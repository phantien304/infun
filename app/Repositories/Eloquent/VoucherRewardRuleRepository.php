<?php

namespace App\Repositories\Eloquent;

use App\Enums\VoucherRewardRuleStatus;
use App\Models\Entities\VoucherRewardRule;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\VoucherRewardRuleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherRewardRuleRepository extends QueryableRepository implements VoucherRewardRuleRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return VoucherRewardRule::class;
    }

    public function listRunningRewardRules(): Collection
    {
        return $this->resetModel()
            ->newQuery()
            ->dateStartToEnd()
            ->where("status", VoucherRewardRuleStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function incrementRewardRuleCount(int $ruleId, int $by = 1): int
    {
        return DB::table('voucher_reward_rule')
            ->where('id', $ruleId)
            ->where(function ($q) use ($by) {
                $q->whereNull('quota_total')
                    ->orWhereRaw('granted_count + ? <= quota_total', [$by]);
            })
            ->whereNull('deleted_at')
            ->increment('granted_count', $by);
    }

    public function decrementRewardRuleCount(int $ruleId, int $by = 1): void
    {
        DB::table('voucher_reward_rule')
            ->where('id', $ruleId)
            ->where('granted_count', '>=', $by)
            ->whereNull('deleted_at')
            ->decrement('granted_count', $by);
    }

    public function flushCache(): void
    {
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'name', 'min_order_total', 'reward_amount', 'date_start', 'date_end', 'sort_order'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted  = (int) $request->input('deleted_at', -1);
        $keyword  = trim((string) $request->input('keyword', ''));
        $perPage  = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        return $query->orderBy($sort, $order)->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * Kèm 200 biên bản phát gần nhất — đủ để soi "ai đã được tặng" mà không
     * kéo cả bảng grant về cho một chương trình chạy lâu.
     */
    public function getForCms(int $id): ?VoucherRewardRule
    {
        return $this->resetModel()
            ->withTrashed()
            ->with([
                'grants' => fn ($q) => $q->with('voucher')->orderByDesc('id')->limit(200),
            ])
            ->find($id);
    }

    /**
     * `granted_count` KHÔNG lấy từ $data — bộ đếm quota chống đua
     * (incrementRewardRuleCount dùng conditional UPDATE). Sửa tay ở CMS là
     * mở đường phát vượt ngân sách.
     */
    public function saveFromCms(?VoucherRewardRule $rule, array $data): VoucherRewardRule
    {
        unset($data['granted_count']);

        $rule ??= new VoucherRewardRule();
        $rule->fill($data);
        $rule->save();

        return $rule;
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?VoucherRewardRule
    {
        $rule = $this->resetModel()->withTrashed()->find($id);
        $rule?->restore();

        return $rule;
    }
}
