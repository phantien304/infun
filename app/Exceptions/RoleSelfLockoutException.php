<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Chống tự khoá (docs/ROLE-PERMISSION-PLAN.md mục 0.3 / Phase 2.2): ném khi
 * user hiện tại đang sửa CHÍNH role mình thuộc, và sau khi lưu (đổi tên +
 * sync quyền) sẽ khiến chính họ mất quyền 'edit-role' — không còn tự sửa
 * lại được. RoleController bắt exception này, trả 422.
 */
class RoleSelfLockoutException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'Không thể lưu: thao tác này sẽ khiến chính bạn mất quyền edit-role và không thể tự sửa lại role này nữa.',
        );
    }
}
