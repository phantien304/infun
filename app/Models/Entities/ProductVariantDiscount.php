<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chiết khấu theo số lượng mua (quantity-tier), theo TỪNG VARIANT — thay thế
 * `product_discount` legacy OpenCart (product-level, xem migration
 * 2026_08_02_000000_create_product_variant_discount_table). Mirror
 * ProductVariantSpecial + thêm `quantity`.
 */
class ProductVariantDiscount extends Base
{
    use SoftDeletes;

    protected $table = 'product_variant_discount';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    // Fillable thật (2026-08-03) — đối chiếu migration create +
    // ProductVariantWriter::syncVariantDiscounts().
    protected $fillable = [
        'product_variant_id', 'product_id', 'user_group_id',
        'quantity', 'priority', 'price', 'date_start', 'date_end',
        // 'deleted_at': SoftDeletes::restore() set null rồi gọi $this->save() —
        // Base::save() funnel getDirty() qua fill(), thiếu cột này restore() sẽ
        // ném MassAssignmentException ngoài production.
        'deleted_at',
    ];

    protected $casts = [
        'date_start'    => 'datetime',
        'date_end'      => 'datetime',
        'price'         => 'float',
        'priority'      => 'integer',
        'quantity'      => 'integer',
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
