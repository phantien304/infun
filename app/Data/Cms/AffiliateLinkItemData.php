<?php

namespace App\Data\Cms;

use App\Models\Entities\AffiliateLink;
use Spatie\LaravelData\Data;

/**
 * Short link của KOL (`affiliate_link`) — CHỈ ĐỌC ở CMS.
 *
 * Link do chính KOL tạo trong cổng affiliate; admin xem để đối soát nguồn
 * traffic, không sửa hộ. `sub_id` là kênh KOL tự đặt (bio IG / TikTok…).
 */
class AffiliateLinkItemData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $destination_url,
        public ?int $product_id,
        public ?string $sub_id,
        public int $clicks_count,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(AffiliateLink $link): self
    {
        return new self(
            id: (int) $link->id,
            slug: (string) $link->slug,
            destination_url: (string) $link->destination_url,
            product_id: $link->product_id === null ? null : (int) $link->product_id,
            sub_id: $link->sub_id,
            clicks_count: (int) $link->clicks_count,
            created_at: $link->created_at?->toDateTimeString(),
        );
    }
}
