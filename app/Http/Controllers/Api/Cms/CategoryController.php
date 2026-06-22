<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CategoryData;
use App\Http\Requests\Cms\CategoryRequest;
use App\Models\Entities\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Http\Request;

/**
 * Category API (REST) cho CMS.
 * -----------------------------------------------------------
 * Controller chỉ điều phối: request → repository → DTO. KHÔNG chứa code
 * tương tác DB (query/transaction nằm ở CategoryRepository — house style).
 *
 * Response: App\Data\Cms\CategoryData (Spatie Data, snake_case).
 * Validate: CategoryRequest. Phân quyền: middleware cms.permission.
 * -----------------------------------------------------------
 */
class CategoryController extends BaseCmsController
{
    protected string $permission = 'category';

    public function __construct(
        private readonly CategoryRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return CategoryData::collect($this->repo->listForCms($request));
    }

    public function store(CategoryRequest $request)
    {
        $category = $this->repo->saveFromCms(null, $request->validated());

        return response()->json(['data' => CategoryData::from($category)], 201);
    }

    public function show(Category $category)
    {
        return response()->json(['data' => CategoryData::from($category->load('descriptions'))]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category = $this->repo->saveFromCms($category, $request->validated());

        return response()->json(['data' => CategoryData::from($category)]);
    }

    public function destroy(Category $category)
    {
        $this->repo->deleteByIds([$category->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $category = $this->repo->restoreById((int) $id);
        abort_if($category === null, 404);

        return response()->json(['data' => CategoryData::from($category)]);
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
