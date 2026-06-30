<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\ProductData;
use App\Data\Cms\ProductListData;
use App\Http\Requests\Cms\ProductRequest;
use App\Models\Entities\Product;
use App\Repositories\Interfaces\ProductCmsRepositoryInterface;
use App\Services\Product\ProductWriteService;
use Illuminate\Http\Request;

/**
 * Product API (REST) cho CMS. Thin controller:
 *   read  → ProductRepository (no cache)
 *   write → ProductWriteService (transaction; variant cluster ở ProductVariantWriter)
 *   output→ ProductData / ProductListData (Spatie Data, snake_case)
 *   validate → ProductRequest
 * -----------------------------------------------------------
 */
class ProductController extends BaseCmsController
{
    protected string $permission = 'product';

    public function __construct(
        private readonly ProductCmsRepositoryInterface $repo,
        private readonly ProductWriteService $writeService,
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

    public function store(ProductRequest $request)
    {
        $product = $this->writeService->save(null, $request->validated());

        return response()->json(
            ['data' => ProductData::from($this->repo->getForCms($product->id))],
            201
        );
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product = $this->writeService->save($product, $request->validated());

        return response()->json(['data' => ProductData::from($this->repo->getForCms($product->id))]);
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

    /** Sửa inline hàng loạt từ trang list (model/badge/price/quantity). */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'items'   => 'required|array|min:1',
            'items.*.id' => 'required|integer',
        ]);

        $affected = $this->writeService->bulkUpdate($data['items']);

        return response()->json(['affected' => $affected]);
    }

    /** Duyệt product từ bản nháp (ProductDraft) — feature riêng, làm sau. */
    public function approve($id)
    {
        return response()->json(['message' => 'Chưa hỗ trợ duyệt product (draft)'], 501);
    }
}
