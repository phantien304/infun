<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Setting;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\SettingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class SettingRepository extends QueryableRepository implements SettingRepositoryInterface
{
    public function model(): string
    {
        return Setting::class;
    }

    public function listAllCached(): array
    {
        return Cache::remember(
            getCoreConfig('cache.setting'),
            now()->addDays(30),
            fn () => $this->resetModel()->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->parsed_value])
                ->all()
        );
    }

    public function listPublicCached(): array
    {
        return Cache::remember(
            getCoreConfig('cache.setting').':cms_public',
            now()->addDays(30),
            fn () => $this->resetModel()->where('cms_public', 1)->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->parsed_value])
                ->all()
        );
    }

    public function flushCache(): void
    {
        Cache::forget(getCoreConfig('cache.setting'));
        Cache::forget(getCoreConfig('cache.setting').':cms_public');
    }
}
