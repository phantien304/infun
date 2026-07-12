<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewTag;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewTagRepositoryInterface;
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
        $this->resetModel()->whereIn('id', $tagIds)->increment('usage_count');
    }
}
