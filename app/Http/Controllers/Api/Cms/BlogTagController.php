<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\BlogTagData;
use App\Http\Requests\Cms\BlogTagRequest;
use App\Models\Entities\BlogTag;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class BlogTagController extends BaseCmsController
{
    protected string $permission = 'blog-tag';

    public function __construct(
        private readonly BlogTagRepositoryInterface $repo
    ) {
    }

    /**
     * 2 chế độ trên CÙNG 1 route (giống CarrierController::index()):
     *  - KHÔNG có query param nào → mảng phẳng {id,title} từ cache, không
     *    phân trang (dropdown cho nơi khác cần chọn tag).
     *  - CÓ ít nhất 1 param list chuẩn → màn CMS list mới (FormSearch+Pager).
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return BlogTagData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($t) => [
                'id'    => $t->id,
                'title' => $t->description?->title ?? '',
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(BlogTagRequest $request)
    {
        $tag = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(BlogTagData::fromModel($tag), 'blog_tag_created');
    }

    public function show(BlogTag $blogTag)
    {
        return respondSuccess(BlogTagData::fromModel($blogTag->load('descriptions')));
    }

    public function update(BlogTagRequest $request, BlogTag $blogTag)
    {
        $tag = $this->repo->saveFromCms($blogTag, $request->validated());

        return respondSuccess(BlogTagData::fromModel($tag), 'blog_tag_updated');
    }

    public function destroy(BlogTag $blogTag)
    {
        $this->repo->deleteByIds([$blogTag->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $tag = $this->repo->restoreById((int) $id);
        abort_if($tag === null, 404);

        return respondSuccess(BlogTagData::fromModel($tag), 'blog_tag_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->repo->deleteByIds($data['ids'])
            : $this->repo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
