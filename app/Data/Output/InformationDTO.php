<?php

namespace App\Data\Output;

use App\Data\Concerns\LazyData;
use App\Models\Entities\Information;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * Output cho trang nội dung tĩnh `information`. Mirror `BlogDTO` (rút gọn):
 *  - `content` để `Lazy` (field nặng) — blade truy cập qua `$entity->content()`
 *    đã resolve, KHÔNG đọc thẳng `$entity->content`.
 *  - `url` build qua `buildUrl` + `getModuleConfig('url.information')` (= 'i').
 *  - `slug` fallback từ title qua `resolveSlug` khi description chưa có slug.
 *  - Date format `d/m/Y` (chỉ blade hiển thị / schema crawler) đồng nhất BlogDTO.
 */
class InformationDTO extends Data
{
    use LazyData;

    public function __construct(
        public int $id,
        public string $title,
        public ?string $slug,
        public ?string $description,
        public string $url,
        public string $publishedDate,
        public string $modifiedDate,
        public Lazy|string $content,
        public string $metaTitle,
        public string $metaDescription,
    ) {}

    public static function fromModel(Information $information): self
    {
        $desc        = $information->description;
        $title       = (string) ($desc->title ?? '');
        $slug        = resolveSlug($desc->slug ?? null, $title);
        $description = (string) ($desc->description ?? '');

        return new self(
            id: (int) $information->id,
            title: $title,
            slug: $slug,
            description: $description,
            url: buildUrl($slug, getModuleConfig('url.information'), (int) $information->id),
            publishedDate: $information->created_at?->format('d/m/Y') ?? '',
            modifiedDate: $information->updated_at?->format('d/m/Y') ?? '',
            content: Lazy::create(fn () => (string) ($desc->content ?? '')),
            metaTitle: $desc->meta_title ?? '',
            metaDescription: $desc->meta_description ?? '',
        );
    }

    public function content(): string
    {
        return $this->resolveLazy($this->content);
    }
}
