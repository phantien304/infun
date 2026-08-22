<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Repositories\Interfaces\SafetyRepositoryInterface;

class SafetyController extends BaseCmsController
{
    protected string $permission = 'safety';

    public function __construct(
        private readonly SafetyRepositoryInterface $repo
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
