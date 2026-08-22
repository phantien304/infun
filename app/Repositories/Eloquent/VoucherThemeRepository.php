<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\VoucherTheme;
use App\Models\Entities\VoucherThemeDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\VoucherThemeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository voucher_theme — mt219 có VoucherThemeRepository ở
 * `app/Repositories/Cms`, infun gộp về một nơi (xem
 * app/Http/Controllers/Api/Cms/README.md: ranh giới area dừng ở tầng HTTP).
 *
 * KHÔNG dùng CacheableRepository: bảng vài chục dòng, đọc mỗi lần mở form
 * voucher — cache ở đây tốn công invalidate hơn là tiết kiệm.
 */
class VoucherThemeRepository extends QueryableRepository implements VoucherThemeRepositoryInterface
{
    public function model(): string
    {
        return VoucherTheme::class;
    }

    public function listWithDescription(): Collection
    {
        return $this->resetModel()
            ->newQuery()
            ->with(['voucherThemeDescriptions'])
            ->orderBy('id')
            ->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'voucher_theme_description.name' : 'voucher_theme.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('voucher_theme_description', function ($join) use ($lang) {
                $join->on('voucher_theme_description.voucher_theme_id', '=', 'voucher_theme.id')
                    ->where('voucher_theme_description.language_code', '=', $lang);
            })
            ->select('voucher_theme.*', 'voucher_theme_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('voucher_theme_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?VoucherTheme
    {
        return $this->resetModel()->withTrashed()->with(['voucherThemeDescriptions'])->find($id);
    }

    public function saveFromCms(?VoucherTheme $theme, array $data): VoucherTheme
    {
        return DB::transaction(function () use ($theme, $data) {
            $theme ??= new VoucherTheme();
            $theme->image = $data['image'];
            $theme->save();

            foreach ((array) ($data['voucher_theme_descriptions'] ?? []) as $item) {
                $this->saveDescription((int) $theme->id, $item);
            }

            return $theme->load(['voucherThemeDescriptions']);
        });
    }

    /**
     * Tên rỗng ở ngôn ngữ phụ ⇒ XOÁ dòng description thay vì lưu chuỗi rỗng
     * (cùng cách ReviewTagRepository làm) — để storefront fallback về ngôn
     * ngữ mặc định thay vì hiện tên trống.
     */
    protected function saveDescription(int $themeId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = VoucherThemeDescription::where('voucher_theme_id', $themeId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new VoucherThemeDescription();
            $desc->voucher_theme_id = $themeId;
            $desc->language_code    = $code;
            $desc->name             = $item['name'];
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

    public function restoreById(int $id): ?VoucherTheme
    {
        $theme = $this->resetModel()->withTrashed()->find($id);
        $theme?->restore();

        return $theme?->load(['voucherThemeDescriptions']);
    }
}
