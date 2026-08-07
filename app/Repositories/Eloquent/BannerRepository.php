<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Banner;
use App\Models\Entities\BannerDescription;
use App\Models\Entities\BannerValue;
use App\Models\Entities\BannerValueDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BannerRepository extends QueryableRepository implements BannerRepositoryInterface
{
    public function model(): string
    {
        return Banner::class;
    }

    public function getBannerByPage($page, $position, $limit = 3, ?string $theme = null): Collection
    {
        return $this->resetModel()->where('page', 'like', '%' . $page . '%')
            ->where('position', $position)
            ->where(function ($q) use ($theme) {
                $q->whereNull('theme');
                if ($theme !== null && $theme !== '') {
                    $q->orWhere('theme', $theme);
                }
            })
            ->with([
                'description',
                'bannerValues' => fn ($q) => $q->orderBy('sort_order'),
                'bannerValues.description',
            ])
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'banner_description.title' : 'banner.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('banner_description', function ($join) use ($lang) {
                $join->on('banner_description.banner_id', '=', 'banner.id')
                    ->where('banner_description.language_code', '=', $lang);
            })
            ->select('banner.*', 'banner_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('banner_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Banner
    {
        return $this->resetModel()->withTrashed()
            ->with(['descriptions', 'bannerValues.descriptions'])
            ->find($id);
    }

    public function saveFromCms(?Banner $banner, array $data): Banner
    {
        return DB::transaction(function () use ($banner, $data) {
            $banner ??= new Banner();
            $banner->type       = $data['type'];
            $banner->position   = $data['position'];
            $banner->sort_order = (int) ($data['sort_order'] ?? 0);
            $banner->page       = implode(',', $data['page'] ?? []);
            $banner->theme      = ! empty($data['theme']) ? $data['theme'] : null;
            $banner->save();

            foreach ((array) ($data['banner_descriptions'] ?? []) as $item) {
                $this->saveBannerDescription($banner->id, $item);
            }

            $this->saveBannerValues($banner->id, (array) ($data['banner_values'] ?? []));

            return $banner->load(['descriptions', 'bannerValues.descriptions']);
        });
    }

    protected function saveBannerDescription(int $bannerId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = BannerDescription::where('banner_id', $bannerId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['title'])) {
            $desc ??= new BannerDescription();
            $desc->banner_id     = $bannerId;
            $desc->language_code = $code;
            $desc->title         = $item['title'];
            $desc->save();
        } elseif ($desc) {
            $desc->delete();
        }
    }

    protected function saveBannerValues(int $bannerId, array $values): void
    {
        $existingIds = BannerValue::where('banner_id', $bannerId)->pluck('id')->all();
        $keptIds = [];

        foreach ($values as $item) {
            $value = ! empty($item['id'])
                ? BannerValue::where('banner_id', $bannerId)->find($item['id'])
                : null;
            $value ??= new BannerValue();

            $mediaType = $item['media_type'] ?? 'image';
            $value->banner_id       = $bannerId;
            $value->link            = $item['link'] ?? null;
            $value->sort_order      = (int) ($item['sort_order'] ?? 0);
            $value->media_type      = $mediaType;
            $value->image           = $item['image'] ?? null;
            $value->video_provider  = $mediaType === 'video' ? ($item['video_provider'] ?? null) : null;
            $value->video_url       = $mediaType === 'video' ? ($item['video_url'] ?? null) : null;
            $value->save();

            $keptIds[] = $value->id;

            foreach ((array) ($item['banner_value_descriptions'] ?? []) as $desc) {
                $this->saveBannerValueDescription($value->id, $desc);
            }
        }

        $removedIds = array_diff($existingIds, $keptIds);
        if ($removedIds) {
            BannerValue::whereIn('id', $removedIds)->delete();
        }
    }

    protected function saveBannerValueDescription(int $bannerValueId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = BannerValueDescription::where('banner_value_id', $bannerValueId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['title']) || ! empty($item['content'])) {
            $desc ??= new BannerValueDescription();
            $desc->banner_value_id = $bannerValueId;
            $desc->language_code   = $code;
            $desc->title           = $item['title'] ?? null;
            $desc->content         = $item['content'] ?? null;
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

    public function restoreById(int $id): ?Banner
    {
        $banner = $this->resetModel()->withTrashed()->find($id);
        $banner?->restore();

        return $banner?->load(['descriptions', 'bannerValues.descriptions']);
    }
}
