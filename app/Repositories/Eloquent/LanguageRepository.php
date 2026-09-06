<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Language;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\LanguageRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LanguageRepository extends QueryableRepository implements LanguageRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Language::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.languages'),
            fn () => $this->resetModel()->orderBy('id')->get()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.languages'));
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['code', 'name', 'priority'], true)
            ? $request->input('sort')
            : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', 1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', '%' . $keyword . '%')
                    ->orWhere('name', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Language
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Language $language, array $data): Language
    {
        $language ??= new Language();
        $language->code      = $data['code'];
        $language->name      = $data['name'] ?? null;
        $language->vi_name   = $data['vi_name'] ?? null;
        $language->priority  = (int) ($data['priority'] ?? 0);
        $language->flag_icon = $data['flag_icon'] ?? null;
        $language->save();

        $this->flushCache();

        return $language;
    }

    /**
     * Mirror mt219 BaseCmsController::_ignoreDelete cho LanguageController
     * (chặn xoá khi chỉ còn đúng 1 ngôn ngữ active) — không được để hệ
     * thống mất sạch ngôn ngữ.
     */
    public function idsBlockedFromDelete(array $ids): array
    {
        $activeCount = $this->resetModel()->count();
        if ($activeCount - count($ids) >= 1) {
            return [];
        }

        return $ids;
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

    public function restoreById(int $id): ?Language
    {
        $language = $this->resetModel()->withTrashed()->find($id);
        $language?->restore();
        $this->flushCache();

        return $language;
    }
}
