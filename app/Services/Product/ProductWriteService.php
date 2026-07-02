<?php

namespace App\Services\Product;

use App\Models\Entities\Option;
use App\Models\Entities\Product;
use App\Models\Entities\ProductAttribute;
use App\Models\Entities\ProductCategory;
use App\Models\Entities\ProductDescription;
use App\Models\Entities\ProductDiscount;
use App\Models\Entities\ProductFilter;
use App\Models\Entities\ProductImage;
use App\Models\Entities\ProductIngredient;
use App\Models\Entities\ProductRelated;
use App\Models\Entities\ProductReward;
use App\Models\Entities\ProductStock;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ProductWriteService
{
    private const FLAT_FIELDS = [
        'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location', 'image',
        'badge', 'date_available', 'link_sale', 'manufacturer_id', 'tax_class_id',
        'stock_status_id', 'shipping', 'is_add_cart', 'is_custom',
        'is_review', 'length', 'width', 'height', 'length_class_id', 'weight',
        'weight_class_id', 'points', 'sort_order',
    ];

    public function __construct(
        private readonly ProductVariantWriter $variantWriter,
        private readonly ProductRepositoryInterface $repo,
    ) {
    }

    public function save(?Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product ??= new Product();

            foreach (self::FLAT_FIELDS as $f) {
                if (array_key_exists($f, $data)) {
                    $product->{$f} = $data[$f] === '' ? null : $data[$f];
                }
            }

            $product->has_variants = collect($data['product_options'] ?? [])
                ->contains(fn ($o) => (int) ($o['role'] ?? 0) === Option::ROLE_VARIANT) ? 1 : 0;

            if (array_key_exists('link_sale_custom', $data)) {
                $lsc = $data['link_sale_custom'];
                $product->link_sale_custom = is_array($lsc) ? json_encode($lsc) : $lsc;
            }

            $product->save();

            $this->syncDescriptions($product, $data['product_descriptions'] ?? []);
            $this->syncCategories($product, $data['product_categories'] ?? []);
            $this->syncFilters($product, $data['product_filters'] ?? []);
            $this->syncRelated($product, $data['product_related'] ?? []);
            $this->syncIngredients($product, $data['product_ingredients'] ?? []);
            $this->syncAttributes($product, $data['product_attributes'] ?? []);
            $this->syncImages($product, $data['product_images'] ?? []);
            $this->syncDiscounts($product, $data['product_discounts'] ?? []);
            $this->syncRewards($product, $data['product_rewards'] ?? []);

            $this->variantWriter->sync(
                $product,
                $data['product_options'] ?? [],
                $data['product_variants'] ?? []
            );

            $this->repo->flushProductCache($product->id);

            return $product;
        });
    }

    public function bulkUpdate(array $items): int
    {
        $count = 0;

        DB::transaction(function () use ($items, &$count) {
            foreach ($items as $it) {
                $id = (int) ($it['id'] ?? 0);
                if (! $id) {
                    continue;
                }
                $product = Product::with('defaultVariant')->find($id);
                if (! $product) {
                    continue;
                }

                if (array_key_exists('model', $it)) {
                    $product->model = $it['model'];
                }
                if (array_key_exists('badge', $it)) {
                    $product->badge = $it['badge'];
                }
                $product->save();

                $variant = $product->defaultVariant;
                if ($variant) {
                    if (($it['price'] ?? '') !== '') {
                        $variant->price = (float) $it['price'];
                        $variant->save();
                    }
                    if (array_key_exists('quantity', $it)) {
                        $stock = ProductStock::where('product_variant_id', $variant->id)->first();
                        if ($stock) {
                            $stock->on_hand = (int) $it['quantity'];
                            $stock->save();
                        }
                    }
                }

                $this->repo->flushProductCache($id);
                $count++;
            }
        });

        return $count;
    }

    protected function syncDescriptions(Product $product, array $items): void
    {
        foreach ($items as $item) {
            $code = $item['language_code'] ?? null;
            if (! $code) {
                continue;
            }
            $desc = ProductDescription::where('product_id', $product->id)
                ->where('language_code', $code)->first();

            if (! empty($item['name'])) {
                $desc ??= new ProductDescription();
                $desc->product_id       = $product->id;
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

    protected function syncCategories(Product $product, array $items): void
    {
        ProductCategory::where('product_id', $product->id)->delete();
        foreach ($items as $it) {
            $cid = (int) ($it['id'] ?? $it['category_id'] ?? 0);
            if (! $cid) {
                continue;
            }
            $row = new ProductCategory();
            $row->product_id  = $product->id;
            $row->category_id = $cid;
            $row->save();
        }
    }

    protected function syncFilters(Product $product, array $ids): void
    {
        ProductFilter::where('product_id', $product->id)->delete();
        foreach (array_unique(array_map('intval', $ids)) as $fid) {
            if (! $fid) {
                continue;
            }
            $row = new ProductFilter();
            $row->product_id      = $product->id;
            $row->filter_value_id = $fid;
            $row->save();
        }
    }

    protected function syncRelated(Product $product, array $items): void
    {
        ProductRelated::where('product_id', $product->id)->delete();
        foreach ($items as $it) {
            $rid = (int) ($it['id'] ?? $it['related_id'] ?? 0);
            if (! $rid || $rid === (int) $product->id) {
                continue;
            }
            $row = new ProductRelated();
            $row->product_id = $product->id;
            $row->related_id = $rid;
            $row->save();
        }
    }

    protected function syncIngredients(Product $product, array $items): void
    {
        ProductIngredient::where('product_id', $product->id)->delete();
        foreach ($items as $it) {
            $iid = (int) ($it['id'] ?? $it['ingredient_id'] ?? 0);
            if (! $iid) {
                continue;
            }
            $row = new ProductIngredient();
            $row->product_id    = $product->id;
            $row->ingredient_id = $iid;
            $row->save();
        }
    }

    protected function syncAttributes(Product $product, array $items): void
    {
        ProductAttribute::where('product_id', $product->id)->delete();
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
                $row->product_id    = $product->id;
                $row->attribute_id  = $attributeId;
                $row->language_code = $code;
                $row->text          = $text;
                $row->save();
            }
        }
    }

    protected function syncImages(Product $product, array $items): void
    {
        ProductImage::where('product_id', $product->id)
            ->whereNull('product_variant_id')->delete();
        foreach ($items as $it) {
            if (empty($it['image'])) {
                continue;
            }
            $row = new ProductImage();
            $row->product_id         = $product->id;
            $row->product_variant_id = null;
            $row->image              = $it['image'];
            $row->sort_order         = (int) ($it['sort_order'] ?? 0);
            $row->save();
        }
    }

    protected function syncDiscounts(Product $product, array $items): void
    {
        ProductDiscount::where('product_id', $product->id)->delete();
        foreach ($items as $it) {
            $row = new ProductDiscount();
            $row->product_id    = $product->id;
            $row->user_group_id = (int) ($it['user_group_id'] ?? 0);
            $row->quantity      = (int) ($it['quantity'] ?? 0);
            $row->priority      = (int) ($it['priority'] ?? 0);
            $row->price         = ($it['price'] ?? '') !== '' ? (float) $it['price'] : 0;
            $row->date_start    = $it['date_start'] ?: null;
            $row->date_end      = $it['date_end'] ?: null;
            $row->save();
        }
    }

    protected function syncRewards(Product $product, array $items): void
    {
        ProductReward::where('product_id', $product->id)->delete();
        foreach ($items as $it) {
            $points = (int) ($it['points'] ?? 0);
            $ugid   = (int) ($it['user_group_id'] ?? 0);
            if (! $ugid || $points <= 0) {
                continue;
            }
            $row = new ProductReward();
            $row->product_id    = $product->id;
            $row->user_group_id = $ugid;
            $row->points        = $points;
            $row->save();
        }
    }
}
