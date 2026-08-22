<?php

namespace App\Http\Controllers\Api\Cms\Marketing;

use App\Data\Cms\VoucherData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\VoucherRequest;
use App\Jobs\VoucherRewardSendEmailJob;
use App\Models\Entities\Voucher;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS voucher (thẻ quà tặng) — thay cho
 * `App\Http\Controllers\Cms\VoucherController` của mt219.
 *
 * Khác biệt so với bản cũ:
 *  - mt219 nhét việc gửi mail vào `save()` bằng cách dò `request()->has(
 *    'send_mails')` — hai nghiệp vụ khác nhau dùng chung 1 endpoint, khó gắn
 *    quyền và khó đọc log. Ở đây tách hẳn `POST voucher/send`.
 *  - Gửi mail dùng `VoucherRewardSendEmailJob` có sẵn: job tự "claim" bằng
 *    `whereNull('sent_at')->update()` nên chạy trùng KHÔNG gửi đôi. Muốn gửi
 *    lại thật thì truyền `force=true` — controller trả `sent_at` về NULL
 *    trước khi dispatch.
 *  - mt219 phân nhánh order/không-order rồi để trống nhánh có order (comment
 *    "// Send mai for order"); ở đây một luồng duy nhất theo `to_email`.
 */
class VoucherController extends BaseCmsController
{
    protected string $permission = 'voucher';

    public function __construct(
        private readonly VoucherRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return VoucherData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(VoucherRequest $request)
    {
        $voucher = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(VoucherData::fromModel($voucher), 'voucher_created');
    }

    public function show($id)
    {
        $voucher = $this->repo->getForCms((int) $id);
        abort_if($voucher === null, 404);

        return respondSuccess(VoucherData::fromModel($voucher));
    }

    public function update(VoucherRequest $request, Voucher $voucher)
    {
        $voucher = $this->repo->saveFromCms($voucher, $request->validated());

        return respondSuccess(VoucherData::fromModel($voucher), 'voucher_updated');
    }

    public function destroy(Voucher $voucher)
    {
        $this->repo->deleteByIds([$voucher->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $voucher = $this->repo->restoreById((int) $id);
        abort_if($voucher === null, 404);

        return respondSuccess(VoucherData::fromModel($voucher), 'voucher_restored');
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

    /**
     * Gửi (hoặc gửi lại) mail voucher cho danh sách id đã chọn ở màn list.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
            'force' => 'nullable|boolean',
        ]);

        if ($request->boolean('force')) {
            $this->repo->clearSent($data['ids']);
        }

        foreach ($data['ids'] as $id) {
            dispatch(new VoucherRewardSendEmailJob((int) $id));
        }

        return respondSuccess(['queued' => count($data['ids'])], 'voucher_send_queued');
    }
}
