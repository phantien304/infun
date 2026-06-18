<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use File;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('channellog', function ($app) {
            return new \App\Helpers\ChannelWriter();
        });
        $this->app->bind('mystorage', 'App\Helpers\MyStorage');
        $this->app->singleton('myrouter', function ($app) {
            return new \App\Helpers\Router($app['router']);
        });
        $this->registerRepository();
    }

    public function boot(): void
    {
        $this->registerViewNamespaces();
        $this->logSql();
        $this->registerObservers();
    }

    protected function registerViewNamespaces(): void
    {
        View::addNamespace('web', resource_path('web/views'));
        View::addNamespace('cms', resource_path('cms/views'));
    }

    protected function registerObservers(): void
    {
        \App\Models\Entities\Review::observe(\App\Observers\ReviewObserver::class);
        \App\Models\Entities\Setting::observe(\App\Observers\SettingObserver::class);
        \App\Models\Entities\ProductVariant::observe(\App\Observers\ProductVariantAggregateObserver::class);
        \App\Models\Entities\ProductVariantSpecial::observe(\App\Observers\ProductVariantAggregateObserver::class);

        $cacheMap = [
            \App\Models\Entities\Product::class               => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductVariant::class        => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductVariantSpecial::class => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductImage::class          => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductCategory::class       => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Category::class     => [\App\Repositories\Interfaces\CategoryRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Manufacturer::class => [\App\Repositories\Interfaces\ManufacturerRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Filter::class       => [\App\Repositories\Interfaces\FilterRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\FilterValue::class  => [\App\Repositories\Interfaces\FilterRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\BlogCategory::class => [\App\Repositories\Interfaces\BlogCategoryRepositoryInterface::class],
            \App\Models\Entities\BlogTag::class      => [\App\Repositories\Interfaces\BlogTagRepositoryInterface::class],
            \App\Models\Entities\Carrier::class => [\App\Repositories\Interfaces\CarrierRepositoryInterface::class],
            \App\Models\Entities\Payment::class => [\App\Repositories\Interfaces\PaymentRepositoryInterface::class],
            \App\Models\Entities\Coupon::class         => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\CouponProduct::class  => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\CouponCategory::class => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\Gift::class                => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\GiftItem::class            => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\GiftTriggerProduct::class  => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\Zone::class => [\App\Repositories\Interfaces\ZoneRepositoryInterface::class],
            \App\Models\Entities\Menu::class      => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],
            \App\Models\Entities\MenuValue::class => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],
            \App\Models\Entities\StoreReview::class => [\App\Repositories\Interfaces\StoreReviewRepositoryInterface::class],
        ];

        $prefix = defined('EVENT_MODEL_TYPE') ? getConstant('EVENT_MODEL_TYPE') : 'eloquent';
        $events = ['saved', 'deleted', 'restored', 'forceDeleted'];

        foreach ($cacheMap as $modelClass => $repoInterfaces) {
            $observer = new \App\Observers\CacheFlushObserver($repoInterfaces);
            foreach ($events as $event) {
                \Illuminate\Support\Facades\Event::listen(
                    "{$prefix}.{$event}: {$modelClass}",
                    function ($model) use ($observer, $event) {
                        $observer->{$event}($model);
                    },
                );
            }
        }
    }

    protected function logSql()
    {
        if ((!env('local') || !env('development')) && !getSystemConfig('sql_log')) {
            return true;
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::listen(function ($sql) {
            try {
                if (\Illuminate\Support\Facades\App::runningInConsole() && strpos($sql->sql, 'jobs') !== false) {
                    return true;
                }
                $messages = ' Time: ' . $sql->time . ' SQL: ' . sql_binding($sql->sql, $sql->bindings);
                logDebug($messages);
            } catch (\Exception $e) {
            } catch (\Error $error) {
            }
        });
    }

    protected function registerRepository()
    {
        $interfacePath = app_path('Repositories/Interfaces');
        if (!File::isDirectory($interfacePath)) {
            return;
        }

        $files = File::allFiles($interfacePath);
        foreach ($files as $file) {
            $interface = 'App\\Repositories\\Interfaces\\' . $file->getBasename('.php');

            $implementation = Str::replaceFirst('Interfaces', 'Eloquent', $interface);
            $implementation = Str::replaceFirst('Interface', '', $implementation);

            if (class_exists($implementation) && !Str::contains($interface, 'BaseRepository')) {
                $this->app->singleton($interface, $implementation);
            }
        }
    }
}
