<?php

namespace App\View\Composers;

use App\Data\Output\CategoryDTO;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\View\View;

/**
 * Bơm `categories` cho cây danh mục sidebar CHỈ khi partial `_side_bar` thật sự
 * render (trang listing) — thay cho việc load ở base `Controller::render()` trên
 * MỌI trang (Phương án B item 5).
 *
 * Manufacturers/filters đã do `FragmentCache` tự resolve (item 3); zones đã ra
 * `/resource/zone` (item 4) → base render() không còn entity list nào. Composer
 * đọc qua `listAllCached()` (systemStore) nên CMS sửa danh mục vẫn fresh ngay
 * (CacheFlushObserver không đổi).
 */
class ProductSidebarComposer
{
    public function __construct(
        protected CategoryRepositoryInterface $categoryRepo,
    ) {
    }

    public function compose(View $view): void
    {
        $view->with('categories', CategoryDTO::collect($this->categoryRepo->listAllCached()));
    }
}
