<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Bảo vệ role hệ thống "Super Admin" (docs/ROLE-PERMISSION-PLAN.md mục Super
 * Admin, thêm 2026-08-05) — ném khi:
 *  1. Sửa/xoá CHÍNH role tên "Super Admin", HOẶC
 *  2. Đặt/đổi tên MỘT role bất kỳ THÀNH "Super Admin"
 * bởi người KHÔNG PHẢI Super Admin. Role này được Gate::before
 * (AppServiceProvider::registerSuperAdminBypass) coi là bypass TOÀN BỘ quyền
 * theo TÊN — nếu để sửa/xoá/đổi tên tự do qua UI thường (dù đã lọc leo thang
 * ở RoleWriteService::filterToOwnPermissions()), một role rỗng quyền tình cờ
 * hoặc cố ý đặt tên "Super Admin" vẫn sẽ bypass được Gate — leo thang toàn
 * quyền mà không cần sở hữu permission nào cả. RoleController bắt exception
 * này, trả 422.
 */
class RoleProtectedException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'Role "Super Admin" là role hệ thống — không thể tạo/sửa/xoá qua CMS. Liên hệ quản trị hạ tầng nếu thật sự cần thay đổi.',
        );
    }
}
