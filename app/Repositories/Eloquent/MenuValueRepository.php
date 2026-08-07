<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\BlogCategory;
use App\Models\Entities\Category;
use App\Models\Entities\Information;
use App\Models\Entities\MenuValue;
use App\Models\Entities\MenuValueDescription;
use App\Models\Entities\Product;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\MenuValueRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuValueRepository extends QueryableRepository implements MenuValueRepositoryInterface
{
    public function model(): string
    {
        return MenuValue::class;
    }

    public function getListMenuValueByMenuId($menuId)
    {
        $this->resetModel();
        return $this->resetModel()
            ->where('menu_id', $menuId)
            ->leftJoin('menu_value_description', 'menu_value_description.menu_value_id', '=', 'menu_value.id')
            ->languageCode('menu_value_description')
            ->select('id', 'item_id', 'parent_id', 'link', 'position', 'type', 'menu_id', 'css', 'html_custom', 'title')
            ->orderBy('position', 'ASC')
            ->get();
    }

    // ===================== CMS (admin) =====================

    public function getTreeForCms(int $menuId): Collection
    {
        $lang = getConfigDb('config_language_admin') ?: 'vi';

        $list = $this->resetModel()
            ->where('menu_value.menu_id', $menuId)
            ->leftJoin('menu_value_description', function ($join) use ($lang) {
                $join->on('menu_value_description.menu_value_id', '=', 'menu_value.id')
                    ->where('menu_value_description.language_code', '=', $lang);
            })
            ->select(
                'menu_value.id',
                'menu_value.item_id',
                'menu_value.parent_id',
                'menu_value.position',
                'menu_value.type',
                'menu_value.css',
                'menu_value.html_custom',
                'menu_value.mega_menu',
                'menu_value.tab_content',
                'menu_value.image',
                'menu_value.menu_id',
                'menu_value_description.title'
            )
            ->orderBy('menu_value.position')
            ->get();

        return $this->attachItemExists($list);
    }

    private function attachItemExists(Collection $list): Collection
    {
        $idsByType = $list
            ->filter(fn ($mv) => $mv->item_id !== null && $mv->type !== null)
            ->groupBy('type')
            ->map(fn ($group) => $group->pluck('item_id')->unique()->values()->all());

        $existingIdsByType = [];
        foreach ($idsByType as $type => $ids) {
            $existingIdsByType[$type] = match ($type) {
                'category'     => Category::whereIn('id', $ids)->pluck('id')->all(),
                'information'  => Information::whereIn('id', $ids)->pluck('id')->all(),
                'product'      => Product::whereIn('id', $ids)->pluck('id')->all(),
                'blogCategory' => BlogCategory::whereIn('id', $ids)->pluck('id')->all(),
                default        => $ids,
            };
        }

        return $list->map(function ($mv) use ($existingIdsByType) {
            $mv->item_exists = $mv->item_id === null
                ? null
                : in_array((int) $mv->item_id, $existingIdsByType[$mv->type] ?? [], true);

            return $mv;
        });
    }

    public function getForCms(int $id): ?MenuValue
    {
        return $this->resetModel()->with('descriptions')->find($id);
    }

    public function saveFromCms(?MenuValue $menuValue, array $data): MenuValue
    {
        return DB::transaction(function () use ($menuValue, $data) {
            $menuValue ??= new MenuValue();
            $menuValue->menu_id     = (int) $data['menu_id'];
            $menuValue->item_id     = $this->nullableInt($data['item_id'] ?? null);
            $menuValue->parent_id   = (int) ($data['parent_id'] ?? 1);
            $menuValue->position    = (int) ($data['position'] ?? 99);
            $menuValue->type        = $data['type'] ?? null;
            $menuValue->css         = $data['css'] ?? null;
            $menuValue->html_custom = $data['html_custom'] ?? null;
            $menuValue->mega_menu   = (int) ($data['mega_menu'] ?? 0);
            $menuValue->tab_content = (int) ($data['tab_content'] ?? 0);
            $menuValue->image       = $data['image'] ?? null;
            $menuValue->status      = 1;
            $menuValue->save();

            foreach ((array) ($data['menu_value_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = MenuValueDescription::where('menu_value_id', $menuValue->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new MenuValueDescription();
                    $desc->menu_value_id = $menuValue->id;
                    $desc->language_code = $code;
                    $desc->title = $item['title'];
                    $desc->link  = $item['link'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $menuValue->load('descriptions');
        });
    }

    public function deleteWithDescendants(int $id): int
    {
        return DB::transaction(function () use ($id) {
            $ids = $this->collectDescendantIds([$id]);
            $count = 0;
            foreach (MenuValue::whereIn('id', $ids)->get() as $mv) {
                $mv->delete();
                $count++;
            }

            return $count;
        });
    }

    public function reorder(int $menuId, array $flat): void
    {
        DB::transaction(function () use ($menuId, $flat) {
            $ids = array_map(fn ($r) => (int) ($r['id'] ?? 0), $flat);
            $models = MenuValue::where('menu_id', $menuId)->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($flat as $row) {
                $id = (int) ($row['id'] ?? 0);
                $mv = $models->get($id);
                if (! $mv) {
                    continue;
                }
                $mv->parent_id = (int) ($row['parent_id'] ?? 1);
                $mv->position  = (int) ($row['position'] ?? 99);
                $mv->save();
            }
        });
    }

    public function importCategoryTree(int $menuId): int
    {
        return DB::transaction(function () use ($menuId) {
            $categories = Category::with('descriptions')->get();
            $byParent   = $categories->groupBy(fn ($c) => (int) $c->parent_id);

            $existing = MenuValue::where('menu_id', $menuId)
                ->where('type', 'category')
                ->whereNotNull('item_id')
                ->pluck('id', 'item_id');

            $created = 0;
            $queue = [[0, 1]];

            while (! empty($queue)) {
                [$catParentId, $mvParentId] = array_shift($queue);
                $siblings = $byParent->get($catParentId, collect());
                $position = 1;

                foreach ($siblings as $cat) {
                    $existingMvId = $existing->get($cat->id);

                    if ($existingMvId) {
                        $queue[] = [$cat->id, $existingMvId];

                        continue;
                    }

                    $mv = new MenuValue();
                    $mv->menu_id     = $menuId;
                    $mv->item_id     = $cat->id;
                    $mv->parent_id   = $mvParentId;
                    $mv->position    = $position++;
                    $mv->type        = 'category';
                    $mv->mega_menu   = 0;
                    $mv->tab_content = 0;
                    $mv->status      = 1;
                    $mv->save();
                    $created++;

                    foreach ($cat->descriptions as $d) {
                        if (empty($d->title)) {
                            continue;
                        }
                        $desc = new MenuValueDescription();
                        $desc->menu_value_id = $mv->id;
                        $desc->language_code = $d->language_code;
                        $desc->title = $d->title;
                        $desc->link  = '';
                        $desc->save();
                    }

                    $queue[] = [$cat->id, $mv->id];
                }
            }

            return $created;
        });
    }

    public function deleteCategoryTree(int $menuId): int
    {
        return DB::transaction(function () use ($menuId) {
            $rootIds = MenuValue::where('menu_id', $menuId)->where('type', 'category')->pluck('id')->all();
            if (empty($rootIds)) {
                return 0;
            }

            $ids = $this->collectDescendantIds($rootIds);
            $count = 0;
            foreach (MenuValue::whereIn('id', $ids)->get() as $mv) {
                $mv->delete();
                $count++;
            }

            return $count;
        });
    }

    public function resolveItemName(string $type, ?int $itemId): ?string
    {
        if (! $itemId) {
            return null;
        }

        return match ($type) {
            'category'     => Category::with('description')->find($itemId)?->description?->title,
            'information'  => Information::with('description')->find($itemId)?->description?->title,
            'product'      => Product::with('description')->find($itemId)?->description?->name,
            'blogCategory' => BlogCategory::with('description')->find($itemId)?->description?->title,
            default        => null,
        };
    }

    /** Bản 1-node của attachItemExists() — dùng cho show/store/update (1 lượt lưu/mở form). */
    public function resolveItemExists(string $type, ?int $itemId): ?bool
    {
        if (! $itemId) {
            return null;
        }

        return match ($type) {
            'category'     => Category::whereKey($itemId)->exists(),
            'information'  => Information::whereKey($itemId)->exists(),
            'product'      => Product::whereKey($itemId)->exists(),
            'blogCategory' => BlogCategory::whereKey($itemId)->exists(),
            default        => null,
        };
    }

    public function renameTitle(int $id, string $title): string
    {
        $title = trim($title);
        $lang  = getConfigDb('config_language') ?: 'vi';

        return DB::transaction(function () use ($id, $title, $lang) {
            $desc = MenuValueDescription::where('menu_value_id', $id)
                ->where('language_code', $lang)
                ->first();

            $desc ??= new MenuValueDescription();
            $desc->menu_value_id  = $id;
            $desc->language_code  = $lang;
            $desc->title          = $title;
            $desc->save();

            return $title;
        });
    }

    private function collectDescendantIds(array $rootIds): array
    {
        $all      = $rootIds;
        $frontier = $rootIds;

        while (! empty($frontier)) {
            $children = MenuValue::whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff($children, $all));
            if (empty($children)) {
                break;
            }
            $all      = array_merge($all, $children);
            $frontier = $children;
        }

        return $all;
    }
}
