<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Phân quyền tự động cho controller REST CMS, dựa trên spatie.
 * -----------------------------------------------------------
 * Tái hiện sự tiện lợi của CheckPermission cũ (tự suy mã quyền theo route)
 * nhưng nguồn quyền là spatie + Gate chuẩn Laravel:
 *
 *   - Đọc tên method của route (index/store/show/update/destroy/restore/bulk)
 *   - Map sang action chuẩn của bạn (list/detail/create/edit/del)
 *   - Lấy slug entity từ controller::permissionName() (BaseCmsController)
 *   - Gate::authorize("{action}-{slug}")  → spatie quyết định, thiếu quyền = 403
 *
 * Chỉ áp dụng cho controller có permissionName() và method nằm trong MAP;
 * route khác (login, me, logout, system/init) không bị ảnh hưởng.
 * -----------------------------------------------------------
 */
class CmsPermission
{
    /** Map method REST/mở rộng → action quyền. */
    protected const MAP = [
        'index'   => 'list',
        'show'    => 'detail',
        'store'   => 'create',
        'update'  => 'edit',
        'destroy' => 'del',
        'restore' => 'del',   // khôi phục dùng chung quyền del
        'bulk'    => 'del',   // xoá/khôi phục hàng loạt
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
                // spatie đăng ký mỗi permission thành ability của Gate.
                Gate::authorize($action . '-' . $slug);
            }
        }

        return $next($request);
    }
}
