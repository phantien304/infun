<?php

namespace App\Http\Controllers\Api\Cms;

use App\Models\Entities\Setting;
use App\Repositories\Interfaces\SettingRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class SettingController extends BaseCmsController
{
    protected string $permission = 'setting';

    public function __construct(
        private readonly SettingRepositoryInterface $settingRepo,
    ) {
    }

    public function show(): JsonResponse
    {
        return respondSuccess($this->settingRepo->listAllCached());
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->all();

        $rows = Setting::query()
            ->where('code', 'config')
            ->whereIn('key', array_keys($payload))
            ->get()
            ->keyBy('key');

        foreach ($payload as $key => $value) {
            $setting = $rows->get($key);
            if (! $setting) {
                continue;
            }

            $serialized = 0;
            if (is_array($value)) {
                $serialized = 1;
                $value = json_encode($value);
            }

            $setting->forceFill([
                'value'      => $value,
                'serialized' => $serialized,
            ])->save();
        }

        return respondSuccess($this->settingRepo->listAllCached(), 'SaveSuccess');
    }

    public function clearCache(): JsonResponse
    {
        if (Cache::getDefaultDriver() === 'redis') {
            Cache::flush();
        }
        File::deleteDirectory(storage_path('framework/cache'));

        return respondSuccess(null, 'DeleteSuccess');
    }
}
