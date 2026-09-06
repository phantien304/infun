<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\LengthClass;
use App\Models\Entities\LengthClassDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\LengthClassRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LengthClassRepository extends QueryableRepository implements LengthClassRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return LengthClass::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.length_classes'),
            fn () => $this->resetModel()->with('descriptions')->orderBy('id')->get(),
            perLocale: false,
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.length_classes'), perLocale: false);
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
        $sort    = $request->input('sort') === 'title' ? 'length_class_description.title' : 'length_class.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('length_class_description', function ($join) use ($lang) {
                $join->on('length_class_description.length_class_id', '=', 'length_class.id')
                    ->where('length_class_description.language_code', '=', $lang);
            })
            ->select('length_class.*', 'length_class_description.title', 'length_class_description.unit');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('length_class_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?LengthClass
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?LengthClass $lengthClass, array $data): LengthClass
    {
        return DB::transaction(function () use ($lengthClass, $data) {
            $lengthClass ??= new LengthClass();
            $lengthClass->value = (float) $data['value'];
            $lengthClass->save();

            foreach ((array) ($data['length_class_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = LengthClassDescription::where('length_class_id', $lengthClass->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new LengthClassDescription();
                    $desc->length_class_id = $lengthClass->id;
                    $desc->language_code   = $code;
                    $desc->title           = $item['title'];
                    $desc->unit            = $item['unit'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            $this->flushCache();

            return $lengthClass->load('descriptions');
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

    public function restoreById(int $id): ?LengthClass
    {
        $lengthClass = $this->resetModel()->withTrashed()->find($id);
        $lengthClass?->restore();
        $this->flushCache();

        return $lengthClass?->load('descriptions');
    }
}
