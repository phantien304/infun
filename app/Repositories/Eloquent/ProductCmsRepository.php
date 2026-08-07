<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Product;
use App\Models\Entities\ProductAttribute;
use App\Models\Entities\ProductDescription;
use App\Models\Entities\ProductFilter;
use App\Models\Entities\ProductImage;
use App\Models\Entities\ProductIngredient;
use App\Models\Entities\ProductRelated;
use App\Models\Entities\ProductReward;
use App\Models\Entities\ProductVariant;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ProductCmsRepositoryInterface;
use App\Services\Stock\WarehouseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class ProductCmsRepository extends QueryableRepository implements ProductCmsRepositoryInterface
{
    public function __construct(
        Application $app,
        private readonly WarehouseService $warehouseService,
    ) {
        parent::__construct($app);
    }

    public function model(): string
    {
        return Product::class;
    }

    protected function cmsDetailRelations(): array
    {
        return [
            'descriptions',
            'productCategories.category.description',
            'productFilters',
            'productRelated.product.description',
            'productIngredients.ingredient.description',
            'productAttributes',
            'productImages',
            'productRewards',
            'productOptions.option',
            'productVariants.productVariantAttributes.option',
            'productVariants.productStocks',
            'productVariants.productVariantSpecials',
            'productVariants.productVariantDiscounts',
            'defaultVariant.productStock',
        ];
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $sortMap = [
            'id'                       => 'product.id',
            'model'                    => 'product.model',
            'badge'                    => 'product.badge',
            'name'                     => 'product_description.name',
            'product_description.name' => 'product_description.name',
        ];
        $sortField  = $request->input('sort', 'id');
        $sortColumn = $sortMap[$sortField] ?? 'product.id';
        $requestedWarehouseId = (int) $request->input('warehouse_id', 0);
        $warehouseIds = $requestedWarehouseId > 0
            ? [$requestedWarehouseId]
            : $this->warehouseService->sellableWarehouseIds();
        $placeholders = implode(',', array_fill(0, count($warehouseIds), '?'));

        $quantitySql = "(
            SELECT COALESCE(SUM(ps.on_hand), 0)
            FROM product_variant pv
            JOIN product_stock ps ON ps.product_variant_id = pv.id
            WHERE pv.product_id = product.id AND pv.deleted_at IS NULL
              AND ps.warehouse_id IN ({$placeholders})
        )";

        $variantCountSql = '(
            SELECT COUNT(*) FROM product_variant pvc
            WHERE pvc.product_id = product.id AND pvc.deleted_at IS NULL
        )';

        $query = Product::query()
            ->leftJoin('product_description', function ($join) use ($lang) {
                $join->on('product_description.product_id', '=', 'product.id')
                    ->where('product_description.language_code', '=', $lang);
            })
            ->select('product.*', 'product_description.name')
            ->selectRaw($quantitySql . ' as agg_quantity', $warehouseIds)
            ->selectRaw($variantCountSql . ' as variant_count')
            ->with(['defaultVariant', 'productDraft']);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('product.model', 'like', '%' . $keyword . '%')
                  ->orWhere('product.sku', 'like', '%' . $keyword . '%');
        }

        if ($sortField === 'quantity') {
            $query->orderByRaw($quantitySql . ' ' . $order, $warehouseIds);
        } else {
            $query->orderBy($sortColumn, $order);
        }

        return $query->paginate($perPage);
    }

    public function getForCms(int $id): ?Product
    {
        return $this->resetModel()->withTrashed()->with($this->cmsDetailRelations())->find($id);
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Product
    {
        $product = $this->resetModel()->withTrashed()->find($id);
        $product?->restore();

        return $product;
    }

    public function saveProduct(Product $product): void
    {
        $product->save();
    }

    public function findWithDefaultVariant(int $id): ?Product
    {
        return $this->resetModel()->with('defaultVariant')->find($id);
    }

    public function saveVariant(ProductVariant $variant): void
    {
        $variant->save();
    }

    public function syncDescriptions(int $productId, array $items): void
    {
        foreach ($items as $item) {
            $code = $item['language_code'] ?? null;
            if (! $code) {
                continue;
            }
            $desc = ProductDescription::where('product_id', $productId)
                ->where('language_code', $code)->first();

            if (! empty($item['name'])) {
                $desc ??= new ProductDescription();
                $desc->product_id       = $productId;
                $desc->language_code    = $code;
                $desc->name             = $item['name'];
                $desc->description      = $item['description'] ?? null;
                $desc->content          = $item['content'] ?? null;
                $desc->tag              = $item['tag'] ?? null;
                $desc->meta_title       = $item['meta_title'] ?? null;
                $desc->meta_description = $item['meta_description'] ?? null;
                $desc->meta_keyword     = $item['meta_keyword'] ?? null;
                $desc->save();
            } elseif ($desc) {
                $desc->delete();
            }
        }
    }

    public function syncFilters(int $productId, array $ids): void
    {
        ProductFilter::where('product_id', $productId)->delete();
        foreach (array_unique(array_map('intval', $ids)) as $fid) {
            if (! $fid) {
                continue;
            }
            $row = new ProductFilter();
            $row->product_id      = $productId;
            $row->filter_value_id = $fid;
            $row->save();
        }
    }

    public function syncRelated(int $productId, array $items): void
    {
        ProductRelated::where('product_id', $productId)->delete();
        foreach ($items as $it) {
            $rid = (int) ($it['id'] ?? $it['related_id'] ?? 0);
            if (! $rid || $rid === $productId) {
                continue;
            }
            $row = new ProductRelated();
            $row->product_id = $productId;
            $row->related_id = $rid;
            $row->save();
        }
    }

    public function syncIngredients(int $productId, array $items): void
    {
        ProductIngredient::where('product_id', $productId)->delete();
        foreach ($items as $it) {
            $iid = (int) ($it['id'] ?? $it['ingredient_id'] ?? 0);
            if (! $iid) {
                continue;
            }
            $row = new ProductIngredient();
            $row->product_id    = $productId;
            $row->ingredient_id = $iid;
            $row->save();
        }
    }

    public function syncAttributes(int $productId, array $items): void
    {
        ProductAttribute::where('product_id', $productId)->delete();
        foreach ($items as $attr) {
            $attributeId = (int) ($attr['attribute_id'] ?? 0);
            if (! $attributeId) {
                continue;
            }
            foreach ($attr['product_attribute'] ?? [] as $val) {
                $code = $val['language_code'] ?? null;
                $text = $val['text'] ?? null;
                if (! $code || ($text === null || $text === '')) {
                    continue;
                }
                $row = new ProductAttribute();
                $row->product_id    = $productId;
                $row->attribute_id  = $attributeId;
                $row->language_code = $code;
                $row->text          = $text;
                $row->save();
            }
        }
    }

    public function syncImages(int $productId, array $items): void
    {
        ProductImage::where('product_id', $productId)
            ->whereNull('product_variant_id')->delete();
        foreach ($items as $it) {
            if (empty($it['image'])) {
                continue;
            }
            $row = new ProductImage();
            $row->product_id         = $productId;
            $row->product_variant_id = null;
            $row->image              = $it['image'];
            $row->sort_order         = (int) ($it['sort_order'] ?? 0);
            $row->save();
        }
    }

    public function syncRewards(int $productId, array $items): void
    {
        ProductReward::where('product_id', $productId)->delete();
        foreach ($items as $it) {
            $points = (int) ($it['points'] ?? 0);
            $ugid   = (int) ($it['user_group_id'] ?? 0);
            if (! $ugid || $points <= 0) {
                continue;
            }
            $row = new ProductReward();
            $row->product_id    = $productId;
            $row->user_group_id = $ugid;
            $row->points        = $points;
            $row->save();
        }
    }
}
