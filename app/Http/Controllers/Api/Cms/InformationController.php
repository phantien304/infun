<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\InformationRepositoryInterface;

/**
 * Information — CHỈ read (dropdown target cho menu-value type=information).
 * CRUD đầy đủ cho information là 1 màn hình riêng, chưa convert (router
 * CRUD_ENTITIES phía infuncms — page 'information' đang placeholder).
 * Permission slug 'information' khớp sp_permissions có sẵn (list-information).
 */
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

        return response()->json(['data' => $data]);
    }
}
