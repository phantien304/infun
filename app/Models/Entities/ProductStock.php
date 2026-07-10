<?php

namespace App\Models\Entities;

use App\Enums\StockPolicy;
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
        'version'          => 'integer',
        'inventory_policy' => StockPolicy::class,
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function policy(): StockPolicy
    {
        return $this->inventory_policy ?? StockPolicy::Deny;
    }

    public function sellableQuantity(): int
    {
        $onHand   = (int) ($this->on_hand ?? 0);
        $reserved = (int) ($this->reserved ?? 0);

        return max(0, $onHand - $reserved);
    }

    public function canSell(int $qty): bool
    {
        if ($this->policy()->bypassesStockCheck()) {
            return true;
        }

        return $this->sellableQuantity() >= $qty;
    }

    public function tracksMovements(): bool
    {
        return $this->policy()->tracksMovements();
    }
}
