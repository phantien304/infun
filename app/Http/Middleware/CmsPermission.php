<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CmsPermission
{
    protected const MAP = [
        'index'   => 'list',
        'show'    => 'detail',
        'store'   => 'create',
        'update'  => 'edit',
        'destroy' => 'del',
        'restore' => 'del',
        'bulk'    => 'del',
        'reorder'          => 'edit',
        'importCategory'   => 'create',
        'deleteCategories' => 'del',
        'previewTotal'     => 'detail',
        'adminList'        => 'list',
        'permissionsRegistry' => 'list',
        'reply'            => 'edit',
        'resolveReport'    => 'edit',
        // Gửi/gửi lại mail voucher — coi như sửa dữ liệu voucher
        // (job đóng dấu `sent_at`), không phải hành động chỉ-đọc.
        'send'             => 'edit',
        // Affiliate (Phase 5). Lưu ý: 'approve' áp cho MỌI controller có
        // method tên đó — kể cả ProductController@approve, vốn trước đây
        // KHÔNG được gate (thiếu entry trong MAP này). Đó là siết đúng chỗ:
        // duyệt sản phẩm vẫn luôn là hành vi sửa dữ liệu.
        'approve'          => 'edit',
        'suspend'          => 'edit',
        'syncCoupons'      => 'edit',
        'preview'          => 'list',
        'closePeriod'      => 'create',
        'markPaid'         => 'edit',
        'cancel'           => 'edit',
        'export'           => 'list',
        'overview'         => 'list',
    ];

    public function handle(Request $request, Closure $next)
    {
        $route      = $request->route();
        $controller = $route?->getController();
        $method     = $route?->getActionMethod();

        if ($controller && \is_object($controller) && \method_exists($controller, 'permissionName')) {
            $slug   = $controller->permissionName();
            $action = self::MAP[$method] ?? null;

            if ($slug !== '' && $action !== null) {
                Gate::authorize($action . '-' . $slug);
            }
        }

        return $next($request);
    }
}
