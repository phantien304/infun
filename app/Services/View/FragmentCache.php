<?php

namespace App\Services\View;

use App\Data\Output\FilterDTO;
use App\Data\Output\ManufacturerDTO;
use App\Helpers\CacheGate;
use App\Repositories\Interfaces\FilterRepositoryInterface;
use App\Repositories\Interfaces\ManufacturerRepositoryInterface;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\View;

class FragmentCache
{
    protected const FACETS_PREFIX = 'frag:sidebar_facets:';

    public static function facets(bool $hideManufacturer = false): string
    {
        $store = CacheGate::systemStore();
        $key = self::facetsKey(app()->getLocale(), $hideManufacturer);

        if (! $store->has($key)) {
            $html = self::buildFacets($hideManufacturer);
            $store->add($key, $html);

            return $html;
        }

        return $store->get($key);
    }

    public static function forgetFacets(): void
    {
        $store = CacheGate::systemStore();
        foreach (config('app.locales', [app()->getLocale()]) as $locale) {
            foreach ([false, true] as $hide) {
                $store->forget(self::facetsKey((string) $locale, $hide));
            }
        }
    }

    protected static function buildFacets(bool $hideManufacturer): string
    {
        $container = Container::getInstance();

        $manufacturers = ManufacturerDTO::collect(
            $container->make(ManufacturerRepositoryInterface::class)->listAllCached()
        );
        $filters = FilterDTO::collect(
            $container->make(FilterRepositoryInterface::class)->listAllCached()
        );

        return View::make('web::category.structure._side_bar_facets_static', [
            'manufacturers'    => $manufacturers,
            'filters'          => $filters,
            'hideManufacturer' => $hideManufacturer,
        ])->render();
    }

    protected static function facetsKey(string $locale, bool $hideManufacturer): string
    {
        return self::FACETS_PREFIX . strtolower($locale) . ':' . ($hideManufacturer ? '1' : '0');
    }
}
