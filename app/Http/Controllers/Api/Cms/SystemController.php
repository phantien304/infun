<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Entities\Language;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    public function init(): JsonResponse
    {
        $texts = trans('rcms');
        if (! is_array($texts)) {
            $texts = [];
        }

        $config = array_merge(
            (array) setting('module.cms.config'),
            (array) getConfigDb()
        );

        // Contract REST thống nhất: { data }.
        return response()->json([
            'data' => [
                'config'        => $config,
                'languageTexts' => $texts,
                'languages'     => Language::query()->get(),
            ],
        ]);
    }
}
