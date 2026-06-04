<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface VoucherRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Resolve voucher code thành mảng thông tin để service tính tiền dùng.
     * Trả [] nếu voucher hết hạn / đã dùng hết amount / chưa được kích hoạt
     * (voucher gắn order phải qua trạng thái complete).
     */
    public function resolveVoucher(?string $code): array;
}
