<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Base
{
    use SoftDeletes;
    protected $table = 'product_image';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'product_id', 'product_variant_id', 'image', 'alt', 'title', 'type',
        'width', 'height', 'file_size', 'mime', 'sort_order', 'is_active',
        'deleted_at',
    ];

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

    public function scopeForProductOnly($query)
    {
        return $query->whereNull('product_variant_id');
    }

    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('product_variant_id', $variantId);
    }
}
