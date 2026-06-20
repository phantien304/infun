<?php

namespace App\Policies;

use App\Models\Entities\Category;
use App\Models\Entities\User;

/**
 * Demo Policy cho hướng spatie/laravel-permission.
 * -----------------------------------------------------------
 * Mỗi method map sang đúng mã quyền cũ của bạn (list/detail/create/edit/del
 * - category). spatie đăng ký mỗi Permission thành 1 "ability" của Gate, nên
 * $user->can('list-category') hoặt Gate::authorize('list-category') chạy được.
 *
 * Cách dùng (2 lựa chọn):
 *   A) Trong controller:  Gate::authorize('list-category');   // không cần policy
 *   B) Qua policy:        $this->authorize('viewAny', Category::class);
 *      (cần base Controller dùng trait AuthorizesRequests — hiện CHƯA có,
 *       nên demo khuyên dùng cách A; policy này để tham khảo cách map.)
 * -----------------------------------------------------------
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('list-category');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('detail-category');
    }

    public function create(User $user): bool
    {
        return $user->can('create-category');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('edit-category');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('del-category');
    }

    // Khôi phục dùng chung quyền 'del' (giống convention cũ: del = xoá/khôi phục).
    public function restore(User $user, Category $category): bool
    {
        return $user->can('del-category');
    }
}
