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

    /**
     * Signature CHÍNH TẮC của một tổ hợp biến thể: MD5 của danh sách
     * "option_id:option_value_id" sort theo option_id, nối bằng '|'.
     * Khớp cột `attribute_signature` + UNIQUE (product_id, attribute_signature).
     *
     * NGUỒN SỰ THẬT DUY NHẤT — mọi nơi GHI variant (admin/seeder) lẫn TRA cứu
     * (CartService::resolveVariantId) PHẢI gọi hàm này, KHÔNG tự viết lại công
     * thức. Lệch 1 ký tự = signature khác = tra không ra variant (add-to-cart fail).
     *
     * @param  array<int,int>  $optionToValue  map [option_id => option_value_id]
     */
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

    // =====================================================================
    // TỒN KHO ĐA KHO (dùng cho hiển thị: trang chi tiết, card, matrix variant)
    //
    // Các hàm dưới TỔNG HỢP tồn qua NHIỀU kho sellable thay vì chỉ đọc row kho
    // mặc định (relation productStock singular). Chúng thao tác trên collection
    // productStocks đã eager-load → không sinh query nếu đã nạp sẵn.
    // Logic khớp CartService / StockService để hiển thị và bán khớp nhau.
    // =====================================================================

    /**
     * Các row tồn thuộc kho ĐANG bán được (is_active && is_sellable).
     *
     * @return Collection<int,ProductStock>
     */
    public function sellableStocks(): Collection
    {
        $rows = $this->productStocks;
        if (! $rows instanceof Collection) {
            return collect();
        }

        return $rows->whereIn('warehouse_id', app(WarehouseService::class)->sellableIds())->values();
    }

    /**
     * Row tồn "hiệu lực" để lấy policy / subtract: ưu tiên kho mặc định,
     * fallback row đầu tiên.
     */
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

    /**
     * Tổng tồn khả bán = Σ (on_hand - reserved) qua mọi kho sellable.
     * KHÔNG áp sentinel policy — dùng để hiển thị SỐ LƯỢNG thực.
     */
    public function sellableQuantityTotal(): int
    {
        return (int) $this->sellableStocks()->sum(fn (ProductStock $s) => $s->sellableQuantity());
    }

    public function hasSellableStock(): bool
    {
        return $this->sellableStocks()->isNotEmpty();
    }

    /**
     * Có bán được $qty không (đa kho): policy bỏ qua kiểm tra tồn (untracked/
     * backorder) → luôn true; ngược lại so với tổng tồn khả bán.
     */
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
