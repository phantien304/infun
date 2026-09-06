<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\PaymentData;
use App\Http\Requests\Cms\PaymentRequest;
use App\Models\Entities\Payment;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class PaymentController extends BaseCmsController
{
    protected string $permission = 'payment';

    public function __construct(
        private readonly PaymentRepositoryInterface $repo
    ) {
    }

    /**
     * 2 chế độ trên CÙNG 1 route (giống CarrierController::index()):
     *  - KHÔNG có query param nào → giữ NGUYÊN hành vi cũ: mảng phẳng
     *    {id,code,name} từ cache, không phân trang (checkout dùng làm dropdown).
     *  - CÓ ít nhất 1 param list chuẩn → màn CMS list mới (FormSearch+Pager).
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return PaymentData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($p) => [
                'id'   => $p->id,
                'code' => $p->code,
                'name' => $p->description?->name ?? '',
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(PaymentRequest $request)
    {
        $payment = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(PaymentData::fromModel($payment), 'payment_created');
    }

    public function show(Payment $payment)
    {
        return respondSuccess(PaymentData::fromModel($payment->load('description')));
    }

    public function update(PaymentRequest $request, Payment $payment)
    {
        $payment = $this->repo->saveFromCms($payment, $request->validated());

        return respondSuccess(PaymentData::fromModel($payment), 'payment_updated');
    }

    public function destroy(Payment $payment)
    {
        $this->repo->deleteByIds([$payment->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $payment = $this->repo->restoreById((int) $id);
        abort_if($payment === null, 404);

        return respondSuccess(PaymentData::fromModel($payment), 'payment_restored');
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
