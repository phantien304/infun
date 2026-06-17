<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

/**
 * product_stock — single source of truth for sellable quantity.
 *
 * All stock-related enums + identifiers live in `config('core.stock')`:
 *  - inventory_policy values        → `stock.policy.{deny|backorder|untracked}`
 *  - stock_movement.type values     → `stock.movement_type.{...}`
 *  - default warehouse for fallback → `stock.default_warehouse_id`
 *
 * Callers always read these through `getCoreConfig('stock.xxx')` instead
 * of hard-coding 0/1/2 or referencing a model constant — same convention
 * as `option.role`, `coupon.type`, and `review.status`.
 */
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

    /**
     * Effective sellable count = max(0, on_hand - reserved).
     *
     * Declared as a regular method (not a `getAvailableAttribute()`
     * accessor) on purpose. Eloquent magic accessors under this project's
     * Base + Compoships + Laravel 12 stack proved unreliable for the
     * `available` name — `$this->available` was returning a stale
     * fallback instead of triggering the accessor. A plain method
     * eliminates the magic and gives every caller a stable, explicit
     * call target.
     *
     * NULL-safe: on_hand / reserved may be missing on legacy rows
     * inserted before the unify migration applied its casts.
     */
    public function sellableQuantity(): int
    {
        $onHand   = (int) ($this->on_hand ?? 0);
        $reserved = (int) ($this->reserved ?? 0);

        return max(0, $onHand - $reserved);
    }

    /**
     * True when add-to-cart should accept an additional `$qty` units.
     * inventory_policy may be NULL when the column hasn't been added yet
     * (migration not run) — fall back to DENY so the gate stays strict.
     */
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

    /** True when this row should record a stock_movement audit entry. */
    public function tracksMovements(): bool
    {
        $policy = (int) ($this->inventory_policy ?? getCoreConfig('stock.policy.deny'));

        return $policy !== (int) getCoreConfig('stock.policy.untracked');
    }
}
