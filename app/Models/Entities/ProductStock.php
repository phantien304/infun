<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductStock extends Base
{
    protected $table = 'product_stock';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'on_hand'          => 'integer',
        'reserved'         => 'integer',
        'subtract'         => 'boolean',
        'inventory_policy' => 'integer',
        'version'          => 'integer',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function sellableQuantity(): int
    {
        $onHand   = (int) ($this->on_hand ?? 0);
        $reserved = (int) ($this->reserved ?? 0);

        return max(0, $onHand - $reserved);
    }

    public function canSell(int $qty): bool
    {
        $policy = (int) ($this->inventory_policy ?? getCoreConfig('stock.policy.deny'));
        if (
            $policy === (int) getCoreConfig('stock.policy.untracked')
            || $policy === (int) getCoreConfig('stock.policy.backorder')
        ) {
            return true;
        }

        return $this->sellableQuantity() >= $qty;
    }

    public function tracksMovements(): bool
    {
        $policy = (int) ($this->inventory_policy ?? getCoreConfig('stock.policy.deny'));

        return $policy !== (int) getCoreConfig('stock.policy.untracked');
    }
}
