<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;

/**
 * Blog Category — CHỈ read (dropdown cho blog/form.jsx chọn danh mục).
 * CRUD đầy đủ cho blog-category là 1 màn hình riêng, chưa convert (xem
 * router CRUD_ENTITIES phía infuncms — page 'blog-category' đang placeholder).
 * Permission slug 'blog-category' khớp sp_permissions có sẵn (list-blog-category).
 */
class BlogCategoryController extends BaseCmsController
{
    protected string $permission = 'blog-category';

    public function __construct(
        private readonly BlogCategoryRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->listAllCached()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'title' => $c->description?->title ?? '',
            ])
            ->values();

        return response()->json(['data' => $data]);
    }
}
