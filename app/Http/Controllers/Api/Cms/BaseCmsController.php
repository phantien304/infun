<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;

/**
 * Base cho controller CMS theo REST chuẩn + phân quyền spatie.
 * -----------------------------------------------------------
 * Controller con chỉ cần:
 *   class CategoryController extends BaseCmsController
 *   {
 *       protected string $permission = 'category';   // slug mã quyền
 *       // index/store/show/update/destroy/restore/bulk ...
 *   }
 *
 * Việc kiểm tra quyền KHÔNG nằm trong từng method — middleware
 * `cms.permission` (CmsPermission) tự đọc tên method của route, map sang
 * action (list/detail/create/edit/del) rồi gọi Gate::authorize("$action-$slug")
 * dựa trên spatie. Nhờ vậy mỗi entity vẫn "0 dòng auth" như middleware tự viết
 * cũ, nhưng nguồn quyền là spatie + chuẩn Gate của Laravel.
 *
 * Gắn middleware qua macro Route::cmsApiResource(...) (xem AppServiceProvider).
 * -----------------------------------------------------------
 */
abstract class BaseCmsController extends Controller
{
    /** Slug dùng để dựng mã quyền: {action}-{permission}. Bắt buộc set ở con. */
    protected string $permission = '';

    /** CmsPermission middleware đọc giá trị này để biết entity. */
    public function permissionName(): string
    {
        return $this->permission;
    }
}
