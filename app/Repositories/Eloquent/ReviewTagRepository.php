<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewTag;
use App\Models\Entities\ReviewTagDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewTagRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewTagRepository extends QueryableRepository implements ReviewTagRepositoryInterface
{
    public function model(): string
    {
        return ReviewTag::class;
    }

    public function idsByCodes(array $codes): Collection
    {
        return $this->resetModel()->active()
            ->whereIn('code', $codes)
            ->pluck('id');
    }

    public function insertPivots(array $rows): void
    {
        DB::table('review_tag_pivot')->insertOrIgnore($rows);
    }

    public function incrementUsage(array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }
        $this->resetModel()->whereIn('id', $tagIds)->increment('usage_count');
    }

    public function removePivots(int $reviewId, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }
        DB::table('review_tag_pivot')->where('review_id', $reviewId)->whereIn('review_tag_id', $tagIds)->delete();
    }

    public function decrementUsage(array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }
        $this->resetModel()->whereIn('id', $tagIds)->where('usage_count', '>', 0)->decrement('usage_count');
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'review_tag_description.name' : 'review_tag.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('review_tag_description', function ($join) use ($lang) {
                $join->on('review_tag_description.review_tag_id', '=', 'review_tag.id')
                    ->where('review_tag_description.language_code', '=', $lang);
            })
            ->select('review_tag.*', 'review_tag_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('review_tag_description.name', 'like', '%' . $keyword . '%')
                    ->orWhere('review_tag.code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?ReviewTag
    {
        return $this->resetModel()->withTrashed()->with(['descriptions'])->find($id);
    }

    /**
     * `usage_count` KHÔNG nhận từ CMS — cột đếm thật (review_tag_pivot),
     * chỉ increment() qua incrementUsage() khi khách chọn tag lúc review,
     * sửa tay ở CMS sẽ làm sai lệch số liệu thật.
     */
    public function saveFromCms(?ReviewTag $tag, array $data): ReviewTag
    {
        return DB::transaction(function () use ($tag, $data) {
            $tag ??= new ReviewTag();
            $tag->code               = $data['code'];
            $tag->is_auto_generated  = (bool) ($data['is_auto_generated'] ?? false);
            $tag->is_active          = (bool) ($data['is_active'] ?? true);
            $tag->save();

            foreach ((array) ($data['review_tag_descriptions'] ?? []) as $item) {
                $this->saveDescription($tag->id, $item);
            }

            return $tag->load(['descriptions']);
        });
    }

    protected function saveDescription(int $tagId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = ReviewTagDescription::where('review_tag_id', $tagId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new ReviewTagDescription();
            $desc->review_tag_id = $tagId;
            $desc->language_code = $code;
            $desc->name          = $item['name'];
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

    public function restoreById(int $id): ?ReviewTag
    {
        $tag = $this->resetModel()->withTrashed()->find($id);
        $tag?->restore();

        return $tag?->load(['descriptions']);
    }
}
