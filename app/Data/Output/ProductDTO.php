<?php

namespace App\Data\Output;

use App\Data\Concerns\HasThumbnail;
use App\Models\Entities\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class ProductDTO extends Data
{
    use HasThumbnail;

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
            rating: $product->rating,
            totalRating: $product->total_rating,
            viewed: $product->viewed,
            isAddCart: $product->is_add_cart,
            isCustom: $product->is_custom,
            isReview: $product->is_review,
            name: $name,
            description: $description,
            slug: $slug,
            excerpt: Str::limit(strip_tags($description), 150),
            priceLabel: self::formatPrice((float) $product->price),
            stockLabel: self::formatStock($product),
            url: buildUrl($slug, getModuleConfig('url.product'), (int) $product->id),
            publishedDate: $product->created_at?->format('d/m/Y') ?? '',
            modifiedDate: $product->updated_at?->format('d/m/Y') ?? '',
            categories: $categories,
            manufacturer: isset($manufacturer) ? ManufacturerDTO::fromModel($manufacturer) : null,
            productSpecial: isset($productSpecial) ? ProductSpecialDTO::fromModel($productSpecial, (float) $product->price) : null,
            matchedFilterNames: $matchedFilterNames,
            ratingRounded: (int) round((float) $product->rating),
            weightUnit: $weightUnit,
            gallery: $gallery,
            linkSaleCustom: $linkSaleCustom,
            content: Lazy::create(fn () => (string) ($desc->content ?? '')),
            tag: Lazy::create(fn () => (string) ($desc->tag ?? '')),
            metaTitle: Lazy::create(fn () => (string) ($desc->meta_title ?? '')),
            metaDescription: Lazy::create(fn () => (string) ($desc->meta_description ?? '')),
        );
    }

    /**
     * Gallery cho trang chi tiết: ảnh chính + ảnh phụ. Chỉ build khi
     * productImages đã được eager-load (tránh N+1 ở list page).
     */
    private static function resolveGallery(Product $product): array
    {
        $gallery = [
            ['key' => 'p'.$product->id, 'image' => (string) $product->image],
        ];
        if (! $product->relationLoaded('productImages')) {
            return $gallery;
        }
        foreach ($product->productImages as $img) {
            $gallery[] = [
                'key' => 'pImg'.$img->id,
                'image' => (string) $img->image,
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

    private static function formatPrice(float $price): string
    {
        return $price > 0
            ? number_format($price) . getConfigDb('config_currency')
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
