<?php

namespace App\Enums;

/**
 * Khớp config('core.user.type.*') (config/core/config.php) — value TRÙNG
 * cột `user.type` trong DB, KHÔNG đổi số nếu đổi tên case (dữ liệu thật
 * đang lưu số 1/2). Thêm theo gợi ý user lúc build màn Customer CMS
 * (docs/ROLE-PERMISSION-PLAN.md, phase mở rộng sau Phase 4) — trước đó
 * UserRepository dùng hằng số `TYPE_ADMIN = 1` rời rạc, dễ lệch nếu nơi
 * khác tự khai `2` cho member mà không tham chiếu cùng 1 nguồn.
 */
enum UserType: int
{
    case Admin = 1;
    case Member = 2;
}
