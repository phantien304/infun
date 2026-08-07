<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Chặn xoá role còn user gán (docs/ROLE-PERMISSION-PLAN.md Phase 2.2) — xoá
 * thẳng sẽ để lại row mồ côi ở model_has_roles (spatie KHÔNG tự dọn khi
 * Role::delete(), và sp_roles không có deleted_at nên đây là XOÁ CỨNG,
 * không hoàn tác được). RoleController bắt exception này, trả 422.
 */
class RoleInUseException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'Không thể xoá: vẫn còn tài khoản đang được gán role này.',
        );
    }
}
