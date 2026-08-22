<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewCriteria;
use App\Models\Entities\ReviewCriteriaDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewCriteriaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewCriteriaRepository extends QueryableRepository implements ReviewCriteriaRepositoryInterface
{
    public function model(): string
    {
        return ReviewCriteria::class;
    }

    public function idsByCodes(array $codes): Collection
    {
        return $this->resetModel()->active()
            ->whereIn('code', $codes)
            ->pluck('id', 'code');
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'review_criteria_description.name' : 'review_criteria.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('review_criteria_description', function ($join) use ($lang) {
                $join->on('review_criteria_description.review_criteria_id', '=', 'review_criteria.id')
                    ->where('review_criteria_description.language_code', '=', $lang);
            })
            ->select('review_criteria.*', 'review_criteria_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('review_criteria_description.name', 'like', '%' . $keyword . '%')
                    ->orWhere('review_criteria.code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?ReviewCriteria
    {
        return $this->resetModel()->withTrashed()->with(['descriptions'])->find($id);
    }

    public function saveFromCms(?ReviewCriteria $criteria, array $data): ReviewCriteria
    {
        return DB::transaction(function () use ($criteria, $data) {
            $criteria ??= new ReviewCriteria();
            $criteria->code        = $data['code'];
            $criteria->icon        = $data['icon'] ?? null;
            $criteria->sort_order  = (int) ($data['sort_order'] ?? 0);
            $criteria->is_required = (bool) ($data['is_required'] ?? false);
            $criteria->is_active   = (bool) ($data['is_active'] ?? true);
            $criteria->save();

            foreach ((array) ($data['review_criteria_descriptions'] ?? []) as $item) {
                $this->saveDescription($criteria->id, $item);
            }

            return $criteria->load(['descriptions']);
        });
    }

    protected function saveDescription(int $criteriaId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = ReviewCriteriaDescription::where('review_criteria_id', $criteriaId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new ReviewCriteriaDescription();
            $desc->review_criteria_id = $criteriaId;
            $desc->language_code      = $code;
            $desc->name               = $item['name'];
            $desc->hint               = $item['hint'] ?? null;
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

    public function restoreById(int $id): ?ReviewCriteria
    {
        $criteria = $this->resetModel()->withTrashed()->find($id);
        $criteria?->restore();

        return $criteria?->load(['descriptions']);
    }
}
