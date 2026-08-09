<?php

namespace App\Http\Controllers\Api\Cms\Order;

use App\Data\Cms\OrderData;
use App\Data\Cms\OrderListData;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\OrderRequest;
use App\Models\Entities\Orders;
use App\Repositories\Interfaces\OrderCmsRepositoryInterface;
use App\Services\Order\OrderAdminWriteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Order API (REST) cho CMS. Thin controller:
 *   read  → OrderCmsRepository (listForCms/getForCms)
 *   write → OrderAdminWriteService (transaction; resolve giá/kho lại từ DB)
 *   output→ OrderListData / OrderData (Spatie Data, snake_case)
 *   validate → OrderRequest
 *
 * Convert từ mt219 app/Http/Controllers/Cms/OrderController.php — khác biệt
 * lớn nhất: KHÔNG còn `step_order`/`mode_save` (wizard FE tự quản lý state,
 * chỉ gọi BE để (1) xem trước tổng tiền — previewTotal, và (2) lưu 1 lần —
 * store/update). Xem OrderAdminWriteService::class-doc để biết phần nào CHƯA
 * làm (coupon/voucher/reward, tồn kho khi SỬA sản phẩm 1 đơn đã tồn tại).
 */
class OrderController extends BaseCmsController
{
    protected string $permission = 'order';

    public function __construct(
        private readonly OrderCmsRepositoryInterface $repo,
        private readonly OrderAdminWriteService $writeService,
    ) {
    }

    public function index(Request $request)
    {
        return OrderListData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function show($id)
    {
        $order = $this->repo->getForCms((int) $id);
        abort_if($order === null, 404);

        return respondSuccess(OrderData::from($order), 'order_shown');
    }

    public function store(OrderRequest $request)
    {
        [$order, $shippingFeeOk] = $this->persist(null, $request->validated());

        return response()->json([
            'data'            => OrderData::from($this->repo->getForCms($order->id)),
            'shipping_fee_ok' => $shippingFeeOk,
        ], 201);
    }

    public function update(OrderRequest $request, Orders $order)
    {
        [$saved, $shippingFeeOk] = $this->persist($order, $request->validated());

        return response()->json([
            'data'            => OrderData::from($this->repo->getForCms($saved->id)),
            'shipping_fee_ok' => $shippingFeeOk,
        ]);
    }

    public function destroy(Orders $order)
    {
        $this->repo->deleteByIds([$order->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $order = $this->repo->restoreById((int) $id);
        abort_if($order === null, 404);

        return respondSuccess(OrderData::from($this->repo->getForCms($order->id)), 'order_restored');
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

    public function previewTotal(Request $request)
    {
        $data = $request->validate([
            'products'                                => 'required|array|min:1',
            'products.*.product_id'                   => 'required|integer',
            'products.*.quantity'                      => 'required|integer|min:1',
            'products.*.option_value_ids'               => 'nullable|array',
            'products.*.option_value_ids.*.option_id'   => 'nullable|integer',
            'products.*.option_value_ids.*.value_id'    => 'nullable|integer',
            'products.*.custom_options'                 => 'nullable|array',
            'products.*.custom_options.*.option_id'     => 'nullable|integer',
            'products.*.custom_options.*.value'         => 'nullable|string|max:1000',
            'zone_id'      => 'required|integer',
            'district_id'  => 'required|integer',
            'ward_id'      => 'required|integer',
            'address'      => 'nullable|string|max:500',
            'carrier_code' => 'required|string|max:50',
        ]);

        try {
            $result = $this->writeService->previewTotal($data);
        } catch (InsufficientStockException $e) {
            abort(422, "Không đủ hàng trong kho: còn {$e->available}, yêu cầu {$e->requested}.");
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return respondSuccess($result);
    }

    private function persist(?Orders $existing, array $data): array
    {
        try {
            $result = $this->writeService->save($existing, $data, Auth::id());
        } catch (InsufficientStockException $e) {
            abort(422, "Không đủ hàng trong kho: còn {$e->available}, yêu cầu {$e->requested}.");
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return [$result['order'], $result['shipping_fee_ok']];
    }
}
