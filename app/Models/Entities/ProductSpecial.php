<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductSpecial extends Base
{
    protected $table = 'product_special';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    /**
     * Cast date_start / date_end về Carbon — Eloquent KHÔNG tự cast cột
     * datetime trừ khi khai báo ở $casts (created_at/updated_at được xử lý
     * riêng qua $dates / SoftDeletes). Không cast → repo + DTO nhận string,
     * `?->format()` crash vì toán tử nullsafe chỉ guard NULL.
     *
     * Cast còn cho phép scope `dateStartToEnd` so sánh Carbon ↔ Carbon
     * (Eloquent auto-convert ở binding) thay vì string compare.
     */
    protected $casts = [
        'date_start' => 'datetime',
        'date_end'   => 'datetime',
        'price'      => 'float',
        'priority'   => 'integer',
    ];
}
