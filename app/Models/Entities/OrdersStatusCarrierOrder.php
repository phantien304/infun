<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersStatusCarrierOrder extends Base
{
    protected $table = 'orders_status_carrier_order';
    // FIXME (phát hiện 2026-08-03, chưa sửa — ngoài phạm vi khai báo $fillable):
    // bảng thật KHÔNG có cột `language_code` (chỉ có orders_status_id +
    // carrier_order_status_id, xem CREATE TABLE trong infun_xampp.sql), nên
    // primaryKey khai ở đây tham chiếu 1 cột không tồn tại. Chưa rõ ảnh hưởng
    // thực tế tới save()/find() — cần review riêng trước khi sửa.
    public $primaryKey = ['carrier_order_status_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = false;

    // Khai báo thật 2026-08-03 — xem docs/SCHEMA-CACHE-FILLABLE.md. Chỉ 2 cột
    // này tồn tại thật trong bảng (đều thuộc PK).
    protected $fillable = ['orders_status_id', 'carrier_order_status_id'];

    public function carrierOrderStatus()
    {
        return $this->belongsTo(CarrierOrderStatus::class, 'carrier_order_status_id', 'id');
    }
}
