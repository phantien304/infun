<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\WeightClass;
use App\Models\Entities\WeightClassDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\WeightClassRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WeightClassRepository extends QueryableRepository implements WeightClassRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return WeightClass::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.weight_classes'),
            fn () => $this->resetModel()->with('descriptions')->orderBy('id')->get(),
            perLocale: false,
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.weight_classes'), perLocale: false);
    }

    public function getAll(): Collection
    {
        return $this->resetModel()->query()->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'weight_class_description.title' : 'weight_class.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('weight_class_description', function ($join) use ($lang) {
                $join->on('weight_class_description.weight_class_id', '=', 'weight_class.id')
                    ->where('weight_class_description.language_code', '=', $lang);
            })
            ->select('weight_class.*', 'weight_class_description.title', 'weight_class_description.unit');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('weight_class_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?WeightClass
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?WeightClass $weightClass, array $data): WeightClass
    {
        return DB::transaction(function () use ($weightClass, $data) {
            $weightClass ??= new WeightClass();
            $weightClass->value = (float) $data['value'];
            $weightClass->save();

            foreach ((array) ($data['weight_class_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = WeightClassDescription::where('weight_class_id', $weightClass->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new WeightClassDescription();
                    $desc->weight_class_id = $weightClass->id;
                    $desc->language_code   = $code;
                    $desc->title           = $item['title'];
                    $desc->unit            = $item['unit'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            $this->flushCache();

            return $weightClass->load('descriptions');
        });
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

    public function restoreById(int $id): ?WeightClass
    {
        $weightClass = $this->resetModel()->withTrashed()->find($id);
        $weightClass?->restore();
        $this->flushCache();

        return $weightClass?->load('descriptions');
    }
}
