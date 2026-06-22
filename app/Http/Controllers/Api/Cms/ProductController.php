<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\ProductData;
use App\Data\Cms\ProductListData;
use App\Models\Entities\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\Request;

/**
 * Product API (REST) cho CMS.
 * -----------------------------------------------------------
 * Thin controller: read → repository, output → DTO. KHÔNG chứa DB code.
 *   index/show/destroy/restore/bulk: ĐỢT 1 (read + xoá mềm) — xong.
 *   store/update/bulk-update/approve: ĐỢT 2 (write cluster variant) —
 *     sẽ qua App\Services\Product\ProductWriteService (+ ProductVariantWriter).
 *
 * Output: App\Data\Cms\ProductData / ProductListData (Spatie Data, snake_case).
 * Phân quyền: middleware cms.permission.
 * -----------------------------------------------------------
 */
class ProductController extends BaseCmsController
{
    protected string $permission = 'product';

    public function __construct(
        private readonly ProductRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return ProductListData::collect($this->repo->listForCms($request));
    }

    public function show($id)
    {
        $product = $this->repo->getForCms((int) $id);
        abort_if($product === null, 404);

        return response()->json(['data' => ProductData::from($product)]);
    }

    public function destroy(Product $product)
    {
        $this->repo->deleteByIds([$product->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $product = $this->repo->restoreById((int) $id);
        abort_if($product === null, 404);

        return response()->json(['restored' => true]);
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

    // ----- ĐỢT 2: write side (qua ProductWriteService) -----
    public function store(Request $request)
    {
        return errNoValidator('Chưa hỗ trợ tạo product (đợt 2)', 501);
    }

    public function update(Request $request, $id)
    {
        return errNoValidator('Chưa hỗ trợ sửa product (đợt 2)', 501);
    }

    public function bulkUpdate(Request $request)
    {
        return errNoValidator('Chưa hỗ trợ bulk-update (đợt 2)', 501);
    }

    public function approve($id)
    {
        return errNoValidator('Chưa hỗ trợ duyệt product (đợt 2)', 501);
    }
}
