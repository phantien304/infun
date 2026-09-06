<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\BlogCategoryData;
use App\Http\Requests\Cms\BlogCategoryRequest;
use App\Models\Entities\BlogCategory;
use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class BlogCategoryController extends BaseCmsController
{
    protected string $permission = 'blog-category';

    public function __construct(
        private readonly BlogCategoryRepositoryInterface $repo
    ) {
    }

    /**
     * 2 chế độ trên CÙNG 1 route (giống CarrierController::index()):
     *  - KHÔNG có query param nào → giữ NGUYÊN hành vi cũ: mảng phẳng
     *    {id,title} từ cache, không phân trang (blog form dùng làm dropdown).
     *  - CÓ ít nhất 1 param list chuẩn → màn CMS list mới (FormSearch+Pager).
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return BlogCategoryData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'title' => $c->description?->title ?? '',
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(BlogCategoryRequest $request)
    {
        $category = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(BlogCategoryData::fromModel($category), 'blog_category_created');
    }

    public function show(BlogCategory $blogCategory)
    {
        return respondSuccess(BlogCategoryData::fromModel($blogCategory->load('descriptions')));
    }

    public function update(BlogCategoryRequest $request, BlogCategory $blogCategory)
    {
        $category = $this->repo->saveFromCms($blogCategory, $request->validated());

        return respondSuccess(BlogCategoryData::fromModel($category), 'blog_category_updated');
    }

    public function destroy(BlogCategory $blogCategory)
    {
        $this->repo->deleteByIds([$blogCategory->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $category = $this->repo->restoreById((int) $id);
        abort_if($category === null, 404);

        return respondSuccess(BlogCategoryData::fromModel($category), 'blog_category_restored');
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
