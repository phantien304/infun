<?php

namespace App\Data\Cms;

use App\Models\Entities\MenuValue;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class MenuValueData extends Data
{
    public function __construct(
        public int $id,
        public int $menu_id,
        public ?int $item_id,
        public int $parent_id,
        public int $position,
        public ?string $type,
        public ?string $css,
        public ?string $html_custom,
        public int $mega_menu,
        public int $tab_content,
        public ?string $image,
        public ?string $title,
        public ?string $item_name,
        public ?bool $item_exists,
        public Collection $menu_value_descriptions,
    ) {
    }

    /**
     * @param MenuValue $mv item_name/itemExists KHÔNG tự resolve ở đây (DTO
     *   không query DB) — controller/repo tính rồi truyền vào (itemExists ưu
     *   tiên đọc thẳng $mv->item_exists nếu repo đã gắn sẵn qua
     *   MenuValueRepository::attachItemExists() ở getTreeForCms(), tránh
     *   query lại; param $itemExists chỉ cần truyền tay ở các call site đơn
     *   lẻ như show/store/update). $mv->title đọc từ cột phụ (select join)
     *   khi tới từ getTreeForCms(); rỗng khi tới từ getForCms() (dùng
     *   descriptions thay).
     */
    public static function fromModel(MenuValue $mv, ?string $itemName = null, ?bool $itemExists = null): self
    {
        $title = $mv->title
            ?? ($mv->relationLoaded('descriptions')
                ? $mv->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $mv->id,
            menu_id: (int) $mv->menu_id,
            item_id: $mv->item_id !== null ? (int) $mv->item_id : null,
            parent_id: (int) $mv->parent_id,
            position: (int) $mv->position,
            type: $mv->type,
            css: $mv->css,
            html_custom: $mv->html_custom,
            mega_menu: (int) $mv->mega_menu,
            tab_content: (int) $mv->tab_content,
            image: $mv->image,
            title: $title,
            item_name: $itemName,
            item_exists: $itemExists ?? $mv->item_exists ?? null,
            menu_value_descriptions: $mv->relationLoaded('descriptions')
                ? MenuValueDescriptionData::collect($mv->descriptions, Collection::class)
                : collect(),
        );
    }
}
