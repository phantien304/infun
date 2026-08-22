<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Repositories\Interfaces\SkincareRepositoryInterface;

class SkincareController extends BaseCmsController
{
    protected string $permission = 'skincare';

    public function __construct(
        private readonly SkincareRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->listAll()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        return respondSuccess($data);
    }
}
