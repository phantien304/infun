<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface OrdersStatusRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Dropdown "Tình trạng đơn hàng" theo locale hiện tại, sắp theo id.
     * ĐẶT TÊN getAll() (không phải listAll()) — BaseRepositoryInterface đã
     * khai listAll(?Request $request = null): Eloquent\Collection; trùng tên
     * khác chữ ký (không tham số, trả về Support\Collection) gây PHP Fatal
     * error "Declaration ... must be compatible with ..." ngay khi bootstrap
     * (đã ăn lỗi thật lúc test /order-status trên trình duyệt — mọi request
     * đều "Failed to fetch" vì server 500 ngay từ lúc autoload interface).
     * Đúng theo convention getAll() mà LengthClass/Manufacturer/TaxClass/
     * WeightClassRepositoryInterface đã dùng cho đúng lý do này.
     */
    public function getAll(): Collection;
}
