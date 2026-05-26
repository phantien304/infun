<?php

namespace App\Data\Output;

use App\Data\Concerns\HasThumbnail;
use App\Data\Concerns\LazyData;
use App\Models\Entities\StoreReview;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class StoreReviewDTO extends Data
{
    use HasThumbnail, LazyData;
    public function __construct(
        public int $id,
        public int $viewed,
        public int $productId,
        public ?string $name,
        public ?string $title,
        public ?string $image,
        public ?string $socialIcon,
        public ?string $authorId,
        public string $publishedDate,
        public ?string $modifiedDate,
        public string $diffForHumans,
        public string $url,
        public ?UserDTO $user,
        public Lazy|string $content,
        public ?string $metaTitle,
        public ?string $metaDescription,
    ) {}
    public static function fromModel(StoreReview $storeReview): self
    {
        $name = (string) ($storeReview->name ?? '');
        $slug = resolveSlug(null, $name);
        $desc = $storeReview->description;

        return new self(
            id: (int) $storeReview->id,
            viewed: (int) $storeReview->viewed,
            productId: (int) $storeReview->product_id,
            name: $name,
            title: $desc?->title ?? '',
            image: $storeReview->image,
            socialIcon: $storeReview->social_icon,
            authorId: $storeReview->author_id,
            publishedDate: $storeReview->created_at?->format('d/m/Y') ?? '',
            modifiedDate: $storeReview->updated_at?->format('d/m/Y') ?? '',
            diffForHumans: $storeReview->created_at?->diffForHumans() ?? '',
            user: $storeReview->user
                ? UserDTO::fromModel($storeReview->user)
                : null,
            url: buildUrl($slug, getModuleConfig('url.store_review'), (int) $storeReview->id),
            content: Lazy::create(fn() => (string) ($desc->content ?? '')),
            metaTitle: $storeReview->meta_title,
            metaDescription: $storeReview->meta_description,
        );
    }
    public function content(): string
    {
        return $this->resolveLazy($this->content);
    }
}
