<?php

namespace App\Http\Controllers\Api\Cms\Marketing;

use App\Data\Cms\CouponData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\CouponRequest;
use App\Models\Entities\Coupon;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS coupon (mã giảm giá marketing) — thay cho
 * `App\Http\Controllers\Cms\CouponController` của mt219.
 *
 * Khác biệt đáng kể so với bản cũ:
 *  - mt219 nhặt tên SP/danh mục bằng 2 vòng lặp thủ công trong
 *    `_beforeRender()`; ở đây eager load `products.description` /
 *    `categories.description` (quan hệ mới trên model Coupon) rồi để DTO
 *    phẳng hoá.
 *  - Lịch sử dùng mã kèm luôn trong detail (giới hạn 200 dòng gần nhất) thay
 *    vì paginate(1000) như `_processCouponHistories()`.
 */
class CouponController extends BaseCmsController
{
    protected string $permission = 'coupon';

    public function __construct(
        private readonly CouponRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return CouponData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(CouponRequest $request)
    {
        $coupon = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(CouponData::fromModel($coupon), 'coupon_created');
    }

    public function show($id)
    {
        $coupon = $this->repo->getForCms((int) $id);
        abort_if($coupon === null, 404);

        return respondSuccess(CouponData::fromModel($coupon));
    }

    public function update(CouponRequest $request, Coupon $coupon)
    {
        $coupon = $this->repo->saveFromCms($coupon, $request->validated());

        return respondSuccess(CouponData::fromModel($coupon), 'coupon_updated');
    }

    public function destroy(Coupon $coupon)
    {
        $this->repo->deleteByIds([$coupon->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $coupon = $this->repo->restoreById((int) $id);
        abort_if($coupon === null, 404);

        return respondSuccess(CouponData::fromModel($coupon), 'coupon_restored');
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
