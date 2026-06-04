<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gallery cluster cho product. Extension cho `product.image` (legacy single
 * field) — KHÔNG thay thế. Mỗi row có thể:
 *  - product_variant_id NULL → ảnh dùng chung cho cả product (mọi variant)
 *  - product_variant_id != NULL → ảnh riêng của variant (vd áo xanh có 5 ảnh
 *    riêng để swap khi user click màu xanh)
 *
 * type discriminator phân biệt vai trò:
 *  - main:      ảnh đại diện
 *  - gallery:   ảnh phụ trong slider
 *  - thumbnail: ảnh nhỏ list page (responsive srcset)
 *  - zoom:      ảnh high-res cho lightbox/zoom modal
 *  - 360:       spin view (multi-frame)
 *
 * is_active để tạm ẩn ảnh không cần soft-delete — vd ảnh bị khiếu nại bản quyền,
 * giữ tạm trong DB để audit nhưng không hiển thị frontend.
 */
class ProductImage extends Base
{
    use SoftDeletes;
    protected $table = 'product_image';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'product_variant_id' => 'integer',
        'width'              => 'integer',
        'height'             => 'integer',
        'file_size'          => 'integer',
        'sort_order'         => 'integer',
        'is_active'          => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string|array $type)
    {
        return $query->whereIn('type', (array) $type);
    }

    /**
     * Ảnh thuộc về CẢ product (không gắn variant cụ thể).
     */
    public function scopeForProductOnly($query)
    {
        return $query->whereNull('product_variant_id');
    }

    /**
     * Ảnh thuộc về 1 variant cụ thể.
     */
    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('product_variant_id', $variantId);
    }
}
