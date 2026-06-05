<?php

namespace App\Data\Output;

use App\Data\Concerns\HasThumbnail;
use App\Data\Concerns\LazyData;
use App\Models\Entities\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class ProductDTO extends Data
{
    use HasThumbnail;
    use LazyData;

    public function __construct(
        public int $id,
        public string $model,
        public ?string $sku,
        public ?int $quantity,
        public ?string $badge,
        public ?string $image,
        public ?string $video,
        public ?int $shipping,
        public ?string $linkSale,
        public float $price,
        public ?float $points,
        public ?string $dateAvailable,
        public ?float $weight,
        public ?float $length,
        public ?float $width,
        public ?float $height,
        public ?int $subtract,
        public ?int $minimum,
        public ?float $rating,
        public ?int $totalRating,
        public ?int $viewed,
        public bool $hasVariants,
        public ?float $minVariantPrice,
        public ?float $maxVariantPrice,
        public ?int $maxVariantDiscountPercent,
        public ?int $isAddCart,
        public ?int $isCustom,
        public ?int $isReview,
        public string $name,
        public string $description,
        public ?string $slug,
        public string $excerpt,
        public string $priceLabel,
        public string $stockLabel,
        public string $url,
        public string $publishedDate,
        public string $modifiedDate,
        public Collection $categories,
        public ?ManufacturerDTO $manufacturer,
        public ?ProductSpecialDTO $productSpecial,
        public array $matchedFilterNames,
        public int $ratingRounded,
        public float $ratingAvg,
        public int $reviewCount,
        public array $ratingDistribution,
        public string $weightUnit,
        public array $gallery,
        public array $linkSaleCustom,
        public Lazy|string $content,
        public Lazy|string $tag,
        public Lazy|string $metaTitle,
        public Lazy|string $metaDescription,
    ) {
    }

    public static function fromModel(Product $product): self
    {
        $desc = $product->description;
        $manufacturer = $product->manufacturer;
        $productSpecial = $product->productSpecial;
        $name = (string) ($desc->name ?? '');
        $slug = resolveSlug($desc->slug ?? null, $name);
        $description = (string) ($desc->description ?? '');
        $categories = $product->productCategories
            ->map(fn ($pc) => $pc->category)
            ->filter()
            ->map(fn ($c) => CategoryDTO::fromModel($c))
            ->values();
        $matchedFilterNames = self::resolveMatchedFilterNames($product);
        $gallery = self::resolveGallery($product);
        $weightUnit = $product->relationLoaded('weightClass')
            ? (string) ($product->weightClass?->description?->unit ?? 'gram')
            : 'gram';
        $linkSaleCustom = self::decodeJsonArray($product->link_sale_custom);

        return new self(
            id: $product->id,
            model: $product->model,
            sku: $product->sku,
            quantity: $product->quantity,
            badge: $product->badge,
            image: $product->image,
            video: $product->video,
            shipping: $product->shipping,
            linkSale: $product->link_sale,
            price: $product->price,
            points: $product->points,
            dateAvailable: $product?->date_available,
            weight: $product->weight,
            length: $product->length,
            width: $product->width,
            height: $product->height,
            subtract: $product->subtract,
            minimum: $product->minimum,
            // Legacy `rating` + `total_rating` cột không còn được cập nhật bởi
            // cluster review mới (observer chỉ ghi rating_avg/review_count). Ưu
            // tiên aggregate cache mới, fallback legacy nếu cache rỗng.
            rating: (float) ($product->rating_avg ?? 0) > 0
                ? (float) $product->rating_avg
                : (float) ($product->rating ?? 0),
            totalRating: (int) ($product->review_count ?? 0) > 0
                ? (int) $product->review_count
                : (int) ($product->total_rating ?? 0),
            viewed: $product->viewed,
            hasVariants: (bool) ($product->has_variants ?? false),
            minVariantPrice: isset($product->min_variant_price) ? (float) $product->min_variant_price : null,
            maxVariantPrice: isset($product->max_variant_price) ? (float) $product->max_variant_price : null,
            maxVariantDiscountPercent: isset($product->max_variant_discount_percent)
                ? (int) $product->max_variant_discount_percent : null,
            isAddCart: $product->is_add_cart,
            isCustom: $product->is_custom,
            isReview: $product->is_review,
            name: $name,
            description: $description,
            slug: $slug,
            excerpt: Str::limit(strip_tags($description), 150),
            priceLabel: self::formatPrice($product),
            stockLabel: self::formatStock($product),
            url: buildUrl($slug, getModuleConfig('url.product'), (int) $product->id),
            publishedDate: $product->created_at?->format('d/m/Y') ?? '',
            modifiedDate: $product->updated_at?->format('d/m/Y') ?? '',
            categories: $categories,
            manufacturer: isset($manufacturer) ? ManufacturerDTO::fromModel($manufacturer) : null,
            productSpecial: isset($productSpecial) ? ProductSpecialDTO::fromModel($productSpecial, (float) $product->price) : null,
            matchedFilterNames: $matchedFilterNames,
            // ratingRounded ưu tiên rating_avg (cluster review mới), fallback legacy.
            ratingRounded: (int) round(
                (float) ($product->rating_avg ?? 0) > 0
                    ? (float) $product->rating_avg
                    : (float) ($product->rating ?? 0)
            ),
            ratingAvg: (float) ($product->rating_avg ?? 0),
            reviewCount: (int) ($product->review_count ?? 0),
            ratingDistribution: is_string($product->rating_distribution ?? null)
                ? (json_decode($product->rating_distribution, true) ?: [])
                : (array) ($product->rating_distribution ?? []),
            weightUnit: $weightUnit,
            gallery: $gallery,
            linkSaleCustom: $linkSaleCustom,
            content: Lazy::create(fn () => (string) ($desc->content ?? '')),
            tag: Lazy::create(fn () => (string) ($desc->tag ?? '')),
            metaTitle: Lazy::create(fn () => (string) ($desc->meta_title ?? '')),
            metaDescription: Lazy::create(fn () => (string) ($desc->meta_description ?? '')),
        );
    }

    public function content(): string
    {
        return $this->resolveLazy($this->content);
    }

    public function tag(): string
    {
        return $this->resolveLazy($this->tag);
    }

    public function metaTitle(): string
    {
        return $this->resolveLazy($this->metaTitle);
    }

    public function metaDescription(): string
    {
        return $this->resolveLazy($this->metaDescription);
    }

    private static function resolveGallery(Product $product): array
    {
        $gallery = [
            [
                'key'   => 'p'.$product->id,
                'image' => (string) $product->image,
                'alt'   => '',
            ],
        ];
        if (! $product->relationLoaded('productImages')) {
            return $gallery;
        }

        $visibleTypes = [
            setting('product_image.type.main'),
            setting('product_image.type.gallery'),
            setting('product_image.type.zoom')
        ];

        foreach ($product->productImages as $img) {
            if (! ($img->is_active ?? true)) {
                continue;
            }
            if (($img->product_variant_id ?? null) !== null) {
                continue;
            }
            if (! in_array($img->type ?? 'gallery', $visibleTypes, true)) {
                continue;
            }

            $gallery[] = [
                'key'   => 'pImg'.$img->id,
                'image' => (string) $img->image,
                'alt'   => (string) ($img->alt ?? ''),
            ];
        }
        return $gallery;
    }

    private static function decodeJsonArray(?string $raw): array
    {
        if (! filled($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function resolveMatchedFilterNames(Product $product): array
    {
        $selected = (array) request()->input('filter.filter_value_id', []);
        if (empty($selected) || !$product->relationLoaded('productFilters')) {
            return [];
        }

        return $product->productFilters
            ->filter(fn ($pf) => in_array($pf->filter_value_id, $selected))
            ->map(fn ($pf) => $pf->filterValue?->description?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function formatPrice(Product $product): string
    {
        $currency = getConfigDb('config_currency');

        if (($product->has_variants ?? false) && ($product->min_variant_price ?? null) !== null) {
            $min = (float) $product->min_variant_price;
            $max = (float) ($product->max_variant_price ?? $min);
            if ($min <= 0 && $max <= 0) {
                return getModuleConfig('product.text_contact');
            }
            if ($min === $max) {
                return number_format($min) . $currency;
            }
            return number_format($min) . ' – ' . number_format($max) . $currency;
        }

        $price = (float) $product->price;
        return $price > 0
            ? number_format($price) . $currency
            : getModuleConfig('product.text_contact');
    }

    private static function formatStock(Product $product): string
    {
        if ($product->quantity <= 0) {
            return $product->stockStatus?->name
                ?? getModuleConfig('product.text_outstock');
        }
        if (getConfigDb('config_stock_display')) {
            return (string) $product->quantity;
        }
        return getModuleConfig('product.text_instock');
    }
}
