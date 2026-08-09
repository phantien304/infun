<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\InformationRepositoryInterface;

class InformationController extends BaseCmsController
{
    protected string $permission = 'information';

    public function __construct(
        private readonly InformationRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->listWithDescription()
            ->map(fn ($i) => [
                'id'    => $i->id,
                'title' => $i->description?->title ?? '',
            ])
            ->values();

        return respondSuccess($data);
    }
}
