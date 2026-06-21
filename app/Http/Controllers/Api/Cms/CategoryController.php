<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Requests\Cms\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Entities\Category;
use App\Models\Entities\CategoryDescription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Category API theo REST chuẩn Laravel (apiResource).
 * -----------------------------------------------------------
 *   GET    /rcms/category            → index   (list, phân trang)
 *   POST   /rcms/category            → store   (tạo, 201)
 *   GET    /rcms/category/{category} → show    (chi tiết)
 *   PUT    /rcms/category/{category} → update  (sửa)
 *   DELETE /rcms/category/{category} → destroy (xoá mềm, 204)
 *
 * Mở rộng ngoài 5 method chuẩn (xoá mềm cần thêm):
 *   PATCH  /rcms/category/{id}/restore → restore (khôi phục)
 *   POST   /rcms/category/bulk         → bulk    (xoá/khôi phục hàng loạt)
 *
 * Response qua CategoryResource → { data: ... } / { data:[...], meta, links }.
 * Validate qua CategoryRequest → 422 { message, errors } chuẩn Laravel.
 *
 * Phân quyền: KHÔNG check trong từng method — middleware 'cms.permission'
 * (gắn bởi Route::cmsApiResource) tự map action → quyền spatie theo $permission.
 * -----------------------------------------------------------
 */
class CategoryController extends BaseCmsController
{
    /** Slug mã quyền: list/detail/create/edit/del - category. */
    protected string $permission = 'category';

    /** GET /category — danh sách (search/sort/paginate). */
    public function index(Request $request)
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'title' ? 'category_description.title' : 'category.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1); // -1 tất cả, 1 hiển thị, 0 đã xoá
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = Category::query()
            ->leftJoin('category_description', function ($join) use ($lang) {
                $join->on('category_description.category_id', '=', 'category.id')
                    ->where('category_description.language_code', '=', $lang);
            })
            ->select('category.*', 'category_description.title');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('category_description.title', 'like', '%' . $keyword . '%');
        }

        $paginator = $query->orderBy($sort, $order)->paginate($perPage);

        return CategoryResource::collection($paginator);
    }

    /** POST /category — tạo mới (201 Created). */
    public function store(CategoryRequest $request)
    {
        $category = $this->persist(new Category(), $request);

        return (new CategoryResource($category->load('descriptions')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** GET /category/{category} — chi tiết kèm mọi bản dịch. */
    public function show(Category $category)
    {
        return new CategoryResource($category->load('descriptions'));
    }

    /** PUT/PATCH /category/{category} — cập nhật. */
    public function update(CategoryRequest $request, Category $category)
    {
        $category = $this->persist($category, $request);

        return new CategoryResource($category->load('descriptions'));
    }

    /** DELETE /category/{category} — xoá mềm (204 No Content). */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->noContent();
    }

    /** PATCH /category/{id}/restore — khôi phục bản ghi đã xoá mềm. */
    public function restore($id)
    {
        $category = Category::withTrashed()->findOrFail($id);
        $category->restore();

        return new CategoryResource($category->load('descriptions'));
    }

    /** POST /category/bulk — xoá / khôi phục hàng loạt. */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? Category::whereIn('id', $data['ids'])->delete()
            : Category::withTrashed()->whereIn('id', $data['ids'])->restore();

        return response()->json(['affected' => $affected]);
    }

    /** Lưu entity + upsert descriptions (title rỗng → xoá bản dịch). */
    protected function persist(Category $category, CategoryRequest $request): Category
    {
        return DB::transaction(function () use ($category, $request) {
            $category->parent_id  = (int) $request->input('parent_id', 0);
            $category->sort_order = (int) $request->input('sort_order', 0);
            $category->icon       = $request->input('icon');
            $category->image      = $request->input('image');
            $category->image_icon = $request->input('image_icon');
            $category->save();

            foreach ((array) $request->input('category_descriptions', []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = CategoryDescription::where('category_id', $category->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['title'])) {
                    if (! $desc) {
                        $desc = new CategoryDescription();
                    }
                    $desc->category_id      = $category->id;
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

            return $category;
        });
    }
}
