<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\MenuValue;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface MenuValueRepositoryInterface extends BaseRepositoryInterface
{
    public function getListMenuValueByMenuId($menuId);

    // ----- CMS (admin) -----

    public function getTreeForCms(int $menuId): Collection;

    public function getForCms(int $id): ?MenuValue;

    public function saveFromCms(?MenuValue $menuValue, array $data): MenuValue;

    public function deleteWithDescendants(int $id): int;

    public function reorder(int $menuId, array $flat): void;

    public function importCategoryTree(int $menuId): int;

    public function deleteCategoryTree(int $menuId): int;

    public function resolveItemName(string $type, ?int $itemId): ?string;

    /**
     * Node type=category/product/information/blogCategory trỏ tới 1 entity
     * qua item_id — entity đó có thể đã bị xoá (soft-delete hoặc hard-delete)
     * sau khi node menu được tạo, node vẫn hiển thị bình thường trên cây
     * CMS nhưng link ngoài site sẽ chết không ai biết. Trả null khi type
     * không áp dụng khái niệm này (vd 'page' dùng link tĩnh, không có
     * item_id) hoặc itemId rỗng — true/false chỉ khi thật sự kiểm tra được.
     */
    public function resolveItemExists(string $type, ?int $itemId): ?bool;

    public function renameTitle(int $id, string $title): string;
}
