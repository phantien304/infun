<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\BlogData;
use App\Http\Requests\Cms\BlogRequest;
use App\Models\Entities\Blog;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Blog API (REST) cho CMS.
 * -----------------------------------------------------------
 * Controller chỉ điều phối: request → repository → DTO. KHÔNG chứa code
 * tương tác DB (query/transaction nằm ở BlogRepository — house style,
 * mirror CategoryController).
 *
 * Response: App\Data\Cms\BlogData (Spatie Data, snake_case).
 * Validate: BlogRequest. Phân quyền: middleware cms.permission
 * (permission slug 'blog' — sp_permissions đã có sẵn list/detail/create/edit/del-blog).
 * -----------------------------------------------------------
 */
class BlogController extends BaseCmsController
{
    protected string $permission = 'blog';

    public function __construct(
        private readonly BlogRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        // $into = PaginatedDataCollection bắt buộc — xem comment cùng chỗ ở
        // ProductController::index(), collect() không có $into trả về THẲNG
        // LengthAwarePaginator (shape phẳng của Laravel) chứ không phải
        // {data, meta:{total}} mà frontend cần.
        return BlogData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(BlogRequest $request)
    {
        $blog = $this->repo->saveFromCms(null, $request->validated());

        return response()->json(['data' => BlogData::from($blog)], 201);
    }

    public function show(Blog $blog)
    {
        return response()->json(['data' => BlogData::from($blog->load(['descriptions', 'user']))]);
    }

    public function update(BlogRequest $request, Blog $blog)
    {
        $blog = $this->repo->saveFromCms($blog, $request->validated());

        return response()->json(['data' => BlogData::from($blog)]);
    }

    public function destroy(Blog $blog)
    {
        $this->repo->deleteByIds([$blog->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $blog = $this->repo->restoreById((int) $id);
        abort_if($blog === null, 404);

        return response()->json(['data' => BlogData::from($blog)]);
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

        return response()->json(['affected' => $affected]);
    }
}
