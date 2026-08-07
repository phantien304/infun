<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CategoryData;
use App\Http\Requests\Cms\CategoryRequest;
use App\Models\Entities\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class CategoryController extends BaseCmsController
{
    protected string $permission = 'category';

    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo
    ) {
    }

    public function index(Request $request)
    {
        return CategoryData::collect($this->categoryRepo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(CategoryRequest $request)
    {
        $category = $this->categoryRepo->saveFromCms(null, $request->validated());
        return respondCreated(CategoryData::from($category), 'category_created');
    }

    public function show(Category $category)
    {
        return respondSuccess(CategoryData::from($category->load('descriptions')));
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category = $this->categoryRepo->saveFromCms($category, $request->validated());

        return respondSuccess(CategoryData::from($category), 'category_updated');
    }

    public function destroy(Category $category)
    {
        $this->categoryRepo->deleteByIds([$category->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $category = $this->categoryRepo->restoreById((int) $id);
        abort_if($category === null, 404);

        return respondSuccess(CategoryData::from($category), 'category_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->categoryRepo->deleteByIds($data['ids'])
            : $this->categoryRepo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
