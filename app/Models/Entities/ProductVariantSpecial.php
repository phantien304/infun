<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Campaign giảm giá ở mức variant — mirror ProductSpecial cho cluster
 * variant. Xem migration 2026_06_06_000000 + mục "Schema cluster variant"
 * trong CLAUDE.md để hiểu lý do tách bảng (variant.regular_price chỉ
 * struck-through tĩnh; không có time-bound + user group + priority).
 *
 * Cast theo ProductSpecial — Eloquent KHÔNG tự cast datetime nếu không khai
 * báo $casts; repo + DTO sẽ nhận string làm `?->format()` crash vì nullsafe
 * chỉ guard NULL chứ không guard non-Carbon.
 */
class ProductVariantSpecial extends Base
{
    use SoftDeletes;

    protected $table = 'product_variant_special';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'date_start'    => 'datetime',
        'date_end'      => 'datetime',
        'price'         => 'float',
        'priority'      => 'integer',
        'user_group_id' => 'integer',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
