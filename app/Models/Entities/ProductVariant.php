<?php

namespace App\Models\Entities;

use App\Enums\StockPolicy;
use App\Models\Base\Base;
use App\Services\Stock\WarehouseService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class ProductVariant extends Base
{
    use SoftDeletes;

    protected $table = 'product_variant';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'price'         => 'float',
        'regular_price' => 'float',
        'weight'        => 'float',
        'is_default'    => 'boolean',
        'sort_order'    => 'integer',
        'points'        => 'integer',
    ];

    public static function buildAttributeSignature(array $optionToValue): string
    {
        ksort($optionToValue);
        $parts = [];
        foreach ($optionToValue as $optionId => $valueId) {
            $parts[] = (int) $optionId . ':' . (int) $valueId;
        }

        return md5(implode('|', $parts));
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function productVariantAttributes()
    {
        return $this->hasMany(ProductVariantAttribute::class, 'product_variant_id', 'id');
    }

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class, 'product_variant_id', 'id');
    }

    public function productStock()
    {
        return $this->hasOne(ProductStock::class, 'product_variant_id', 'id')
            ->where('warehouse_id', (int) (getConfigDb('config_warehouse_id') ?: 1));
    }

    public function sellableStocks(): Collection
    {
        $rows = $this->productStocks;
        if (! $rows instanceof Collection) {
            return collect();
        }

        return $rows->whereIn('warehouse_id', app(WarehouseService::class)->sellableWarehouseIds())->values();
    }

    public function effectiveStockRow(): ?ProductStock
    {
        $stocks = $this->sellableStocks();

        return $stocks->firstWhere('warehouse_id', app(WarehouseService::class)->defaultId())
            ?? $stocks->first();
    }

    public function effectiveStockPolicy(): StockPolicy
    {
        return $this->effectiveStockRow()?->policy() ?? StockPolicy::Deny;
    }

    public function sellableQuantityTotal(): int
    {
        return (int) $this->sellableStocks()->sum(fn (ProductStock $s) => $s->sellableQuantity());
    }

    public function hasSellableStock(): bool
    {
        return $this->sellableStocks()->isNotEmpty();
    }

    public function canSellQuantity(int $qty): bool
    {
        $stocks = $this->sellableStocks();
        if ($stocks->isEmpty()) {
            return false;
        }

        if ($this->effectiveStockPolicy()->bypassesStockCheck()) {
            return true;
        }

        return $this->sellableQuantityTotal() >= $qty;
    }

    public function descriptions()
    {
        return $this->hasMany(ProductVariantDescription::class, 'product_variant_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(ProductVariantDescription::class, 'product_variant_id', 'id')->forLocale();
    }

    public function productVariantSpecials()
    {
        return $this->hasMany(ProductVariantSpecial::class, 'product_variant_id', 'id');
    }

    public function productVariantSpecial()
    {
        return $this->hasOne(ProductVariantSpecial::class, 'product_variant_id', 'id')->ofMany(
            ['priority' => 'max'],
            fn ($q) => $q->dateStartToEnd()
                ->where('user_group_id', getUserGroupId())
        );
    }
}
