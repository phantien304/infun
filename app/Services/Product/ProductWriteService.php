<?php

namespace App\Services\Product;

use App\Enums\OptionRole;
use App\Models\Entities\Product;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;
use App\Repositories\Interfaces\ProductCmsRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\ProductStockRepositoryInterface;

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
        private readonly ProductVariantWriter $productVariantWriter,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly ProductCmsRepositoryInterface $productCmsRepo,
        private readonly ProductCategoryRepositoryInterface $productCategoryRepo,
        private readonly ProductStockRepositoryInterface $productStockRepo,
    ) {
    }

    public function save(?Product $product, array $data): Product
    {
        return $this->productCmsRepo->transaction(function () use ($product, $data) {
            $product ??= new Product();

            foreach (self::FLAT_FIELDS as $f) {
                if (array_key_exists($f, $data)) {
                    $product->{$f} = $data[$f] === '' ? null : $data[$f];
                }
            }

            $product->has_variants = collect($data['product_options'] ?? [])
                ->contains(fn ($o) => OptionRole::fromInput($o['role'] ?? null)->isVariant()) ? 1 : 0;

            if (array_key_exists('link_sale_custom', $data)) {
                $lsc = $data['link_sale_custom'];
                $product->link_sale_custom = is_array($lsc) ? json_encode($lsc) : $lsc;
            }

            $this->productCmsRepo->saveProduct($product);

            $this->productCmsRepo->syncDescriptions($product->id, $data['product_descriptions'] ?? []);
            $this->productCategoryRepo->syncForProduct($product->id, $data['product_categories'] ?? []);
            $this->productCmsRepo->syncFilters($product->id, $data['product_filters'] ?? []);
            $this->productCmsRepo->syncRelated($product->id, $data['product_related'] ?? []);
            $this->productCmsRepo->syncIngredients($product->id, $data['product_ingredients'] ?? []);
            $this->productCmsRepo->syncAttributes($product->id, $data['product_attributes'] ?? []);
            $this->productCmsRepo->syncImages($product->id, $data['product_images'] ?? []);
            $this->productCmsRepo->syncDiscounts($product->id, $data['product_discounts'] ?? []);
            $this->productCmsRepo->syncRewards($product->id, $data['product_rewards'] ?? []);

            $this->productVariantWriter->sync(
                $product,
                $data['product_options'] ?? [],
                $data['product_variants'] ?? []
            );

            $this->productRepo->flushProductCache($product->id);

            return $product;
        });
    }

    public function bulkUpdate(array $items): int
    {
        $count = 0;

        $this->productCmsRepo->transaction(function () use ($items, &$count) {
            foreach ($items as $it) {
                $id = (int) ($it['id'] ?? 0);
                if (! $id) {
                    continue;
                }
                $product = $this->productCmsRepo->findWithDefaultVariant($id);
                if (! $product) {
                    continue;
                }

                if (array_key_exists('model', $it)) {
                    $product->model = $it['model'];
                }
                if (array_key_exists('badge', $it)) {
                    $product->badge = $it['badge'];
                }
                $this->productCmsRepo->saveProduct($product);

                $variant = $product->defaultVariant;
                if ($variant) {
                    if (($it['price'] ?? '') !== '') {
                        $variant->price = (float) $it['price'];
                        $this->productCmsRepo->saveVariant($variant);
                    }
                    if (array_key_exists('quantity', $it)) {
                        $this->productStockRepo->updateOnHand($variant->id, (int) $it['quantity']);
                    }
                }

                $this->productRepo->flushProductCache($id);
                $count++;
            }
        });

        return $count;
    }
}
