<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Repositories\Interfaces\EffectRepositoryInterface;

class EffectController extends BaseCmsController
{
    protected string $permission = 'effect';

    public function __construct(
        private readonly EffectRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->listAll()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])
            ->values();

        return respondSuccess($data);
    }
}
