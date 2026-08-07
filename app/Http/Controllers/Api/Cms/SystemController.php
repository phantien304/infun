<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Entities\Language;
use App\Repositories\Interfaces\SettingRepositoryInterface;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settingRepo,
    ) {
    }

    public function init(): JsonResponse
    {
        $texts = trans('rcms');
        if (! is_array($texts)) {
            $texts = [];
        }

        $config = array_merge(
            (array) setting('module.cms.config'),
            $this->settingRepo->listPublicCached()
        );

        return respondSuccess([
            'config'        => $config,
            'languageTexts' => $texts,
            'languages'     => Language::query()->get(),
            'themes'        => array_values((array) config('theme.available', [])),
            'imageDomain'   => rtrim((string) config('filesystems.disks.image.url'), '/'),
        ]);
    }
}
