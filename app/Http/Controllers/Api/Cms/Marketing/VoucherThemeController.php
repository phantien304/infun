<?php

namespace App\Http\Controllers\Api\Cms\Marketing;

use App\Data\Cms\VoucherThemeData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\VoucherThemeRequest;
use App\Models\Entities\VoucherTheme;
use App\Repositories\Interfaces\VoucherThemeRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS voucher theme (mẫu thiệp) — thay cho
 * `App\Http\Controllers\Cms\VoucherThemeController` của mt219 + presenter
 * `PVoucherTheme`. Nghiệp vụ giữ nguyên: ảnh + tên đa ngữ.
 */
class VoucherThemeController extends BaseCmsController
{
    protected string $permission = 'voucher-theme';

    public function __construct(
        private readonly VoucherThemeRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return VoucherThemeData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(VoucherThemeRequest $request)
    {
        $theme = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(VoucherThemeData::fromModel($theme), 'voucher_theme_created');
    }

    public function show($id)
    {
        $theme = $this->repo->getForCms((int) $id);
        abort_if($theme === null, 404);

        return respondSuccess(VoucherThemeData::fromModel($theme));
    }

    public function update(VoucherThemeRequest $request, VoucherTheme $voucherTheme)
    {
        $theme = $this->repo->saveFromCms($voucherTheme, $request->validated());

        return respondSuccess(VoucherThemeData::fromModel($theme), 'voucher_theme_updated');
    }

    public function destroy(VoucherTheme $voucherTheme)
    {
        $this->repo->deleteByIds([$voucherTheme->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $theme = $this->repo->restoreById((int) $id);
        abort_if($theme === null, 404);

        return respondSuccess(VoucherThemeData::fromModel($theme), 'voucher_theme_restored');
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
