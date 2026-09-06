<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\BlogTag;
use App\Models\Entities\BlogTagDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BlogTagRepository extends QueryableRepository implements BlogTagRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return BlogTag::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('cache.blog_tags'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(getCoreConfig('cache.blog_tags'));
    }

    protected function withRelations(): array
    {
        return [
            'description'
        ];
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'blog_tag_description.title' : 'blog_tag.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('blog_tag_description', function ($join) use ($lang) {
                $join->on('blog_tag_description.blog_tag_id', '=', 'blog_tag.id')
                    ->where('blog_tag_description.language_code', '=', $lang);
            })
            ->select('blog_tag.*', 'blog_tag_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('blog_tag_description.title', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?BlogTag
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?BlogTag $tag, array $data): BlogTag
    {
        return DB::transaction(function () use ($tag, $data) {
            $tag ??= new BlogTag();
            $tag->background = $data['background'] ?? null;
            $tag->sort_order = (int) ($data['sort_order'] ?? 0);
            $tag->save();

            foreach ((array) ($data['blog_tag_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = BlogTagDescription::where('blog_tag_id', $tag->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    $desc ??= new BlogTagDescription();
                    $desc->blog_tag_id      = $tag->id;
                    $desc->language_code    = $code;
                    $desc->title            = $item['title'];
                    $desc->description      = $item['description'] ?? null;
                    $desc->meta_title       = $item['meta_title'] ?? null;
                    $desc->meta_description = $item['meta_description'] ?? null;
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            $this->flushCache();

            return $tag->load('descriptions');
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

    public function restoreById(int $id): ?BlogTag
    {
        $tag = $this->resetModel()->withTrashed()->find($id);
        $tag?->restore();
        $this->flushCache();

        return $tag?->load('descriptions');
    }
}
