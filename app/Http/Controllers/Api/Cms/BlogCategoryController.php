<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;

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

        return respondSuccess($data);
    }
}
