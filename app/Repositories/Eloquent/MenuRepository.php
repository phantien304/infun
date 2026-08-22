<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Menu;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\MenuRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuRepository extends QueryableRepository implements MenuRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Menu::class;
    }

    public function getMenuByPosition($position = 'top', ?string $theme = null): Collection
    {
        return $this->resetModel()
            ->where('position', $position)
            ->where(function ($q) use ($theme) {
                $q->whereNull('theme');
                if ($theme !== null && $theme !== '') {
                    $q->orWhere('theme', $theme);
                }
            })
            ->with([
                'menuValues.description'
            ])
            ->get();
    }

    public function flushCache(): void
    {
        $store    = \App\Helpers\CacheGate::systemStore();
        $base     = getCoreConfig('cache.menu');
        $themes   = array_merge(['default'], (array) config('theme.available', []));
        $locales  = config('app.locales', [app()->getLocale()]);

        foreach ($locales as $locale) {
            foreach ($themes as $theme) {
                $store->forget($base . $locale . '_' . $theme);
                $store->forget($base . 'tree_' . $locale . '_' . $theme);
            }
        }
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = $request->input('sort') === 'title' ? 'title' : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Menu
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Menu $menu, array $data): Menu
    {
        return DB::transaction(function () use ($menu, $data) {
            $menu ??= new Menu();
            $menu->title    = $data['title'] ?? $menu->title;
            $menu->position = $data['position'] ?? $menu->position;
            $menu->theme    = ! empty($data['theme']) ? $data['theme'] : null;
            $menu->save();

            return $menu;
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

    public function restoreById(int $id): ?Menu
    {
        $menu = $this->resetModel()->withTrashed()->find($id);
        $menu?->restore();

        return $menu;
    }
}
