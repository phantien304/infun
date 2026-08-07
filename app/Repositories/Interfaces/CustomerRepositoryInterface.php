<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\User;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * CMS Customer (user `type=2` — App\Enums\UserType::Member) — mở rộng sau
 * Phase 4 (docs/ROLE-PERMISSION-PLAN.md), theo quyết định Phase 6.1 gốc:
 * Customer TÁCH RIÊNG khỏi UserRepositoryInterface (chỉ quản `type=1` Admin)
 * vì khác domain nghiệp vụ (CMS-MODULE-BOUNDARY.md nhóm `Customer/` riêng
 * `System/`), dù CÙNG bảng `user` vật lý.
 *
 * Field riêng của Customer so với Admin: phone (qua UserPhoneRepository,
 * KHÔNG đụng lại ở đây — controller tự inject thêm), address, sex,
 * newsletter, user_group_id (nhóm giá/khuyến mãi — xem Common::getUserGroupId(),
 * user_group_id ảnh hưởng TÍNH GIÁ ở ProductVariant/Cart, gán id là thao
 * tác CRUD đơn thuần, KHÔNG tính toán giá ở đây).
 *
 * Chưa làm (out of scope đợt này, xem báo cáo user): quản lý nhiều địa chỉ
 * giao hàng (UserAddress hasMany), affiliate, review — đây là field
 * `address` đơn (cột trực tiếp trên `user`, giống AccountService::updateProfile),
 * không phải bảng user_address.
 */
interface CustomerRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?User;

    public function createCustomer(array $data): User;

    public function updateCustomer(User $user, array $data): User;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?User;
}
