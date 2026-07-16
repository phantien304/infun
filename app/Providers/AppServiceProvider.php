<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use File;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('mystorage', 'App\Helpers\MyStorage');

        $this->registerRepository();

        $this->app->singleton('myrouter', function ($app) {
            return new \App\Helpers\Router($app['router']);
        });
        $this->app->singleton('channellog', function ($app) {
            return new \App\Helpers\ChannelWriter();
        });
        $this->app->scoped(\App\Services\Currency\CurrencyService::class);
        $this->app->scoped(\App\Services\Measurement\LengthService::class);
        $this->app->scoped(\App\Services\Measurement\WeightService::class);
        $this->app->singleton(\App\Services\ConfigDbService::class);
        $this->app->singleton(\App\Services\Stock\WarehouseService::class);
    }

    public function boot(): void
    {
        $this->optimizes('repository:cache', 'repository:clear', 'repositories');
        $this->registerViewNamespaces();
        $this->registerRouteMacros();
        $this->registerRateLimiters();
        $this->logSql();
        $this->registerObservers();
    }

    /**
     * Named rate limiter cho endpoint nóng (routes dùng throttle:add-to-cart...).
     * Mức limit đọc từ config/throttle.php — nới được qua env khi load test.
     *
     * Key theo user đăng nhập → session → IP (thứ tự ưu tiên). KHÔNG key
     * thuần IP: sau LB/CGNAT cả văn phòng chung 1 IP → chặn nhầm khách thật.
     * Lưu ý: cần trustProxies (bootstrap/app.php) để ->ip() ra IP client
     * thật thay vì IP của nginx LB.
     */
    protected function registerRateLimiters(): void
    {
        $keyFor = function (\Illuminate\Http\Request $request): string {
            if ($user = $request->user()) {
                return 'u:' . $user->getAuthIdentifier();
            }
            if ($request->hasSession()) {
                return 's:' . $request->session()->getId();
            }

            return 'ip:' . $request->ip();
        };

        \Illuminate\Support\Facades\RateLimiter::for('add-to-cart', function (\Illuminate\Http\Request $request) use ($keyFor) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute((int) config('throttle.add_to_cart', 30))->by($keyFor($request));
        });

        \Illuminate\Support\Facades\RateLimiter::for('save-order', function (\Illuminate\Http\Request $request) use ($keyFor) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute((int) config('throttle.save_order', 10))->by($keyFor($request));
        });
    }

    protected function registerRouteMacros(): void
    {
        Route::macro('cmsResource', function (string $name, string $controller) {
            Route::get($name . '/list', [$controller, 'list']);
            Route::post($name . '/save', [$controller, 'save']);
            Route::post($name . '/del', [$controller, 'del']);
            Route::get($name . '/{id}', [$controller, 'detail']);
        });
        Route::macro('cmsApiResource', function (string $name, string $controller) {
            Route::middleware('cms.permission')->group(function () use ($name, $controller) {
                Route::patch($name . '/{id}/restore', [$controller, 'restore']);
                Route::post($name . '/bulk', [$controller, 'bulk']);
                Route::apiResource($name, $controller)->withTrashed();
            });
        });
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
        \App\Models\Entities\Orders::observe(\App\Observers\OrderRewardObserver::class);
        \App\Models\Entities\Orders::observe(\App\Observers\OrderAffiliateObserver::class);

        $cacheMap = [
            \App\Models\Entities\Product::class               => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductVariant::class        => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductVariantSpecial::class => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductImage::class          => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductCategory::class       => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Category::class              => [\App\Repositories\Interfaces\CategoryRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Manufacturer::class          => [\App\Repositories\Interfaces\ManufacturerRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Filter::class                => [\App\Repositories\Interfaces\FilterRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\FilterValue::class           => [\App\Repositories\Interfaces\FilterRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\BlogCategory::class          => [\App\Repositories\Interfaces\BlogCategoryRepositoryInterface::class],
            \App\Models\Entities\BlogTag::class               => [\App\Repositories\Interfaces\BlogTagRepositoryInterface::class],
            \App\Models\Entities\Carrier::class               => [\App\Repositories\Interfaces\CarrierRepositoryInterface::class],
            \App\Models\Entities\Payment::class               => [\App\Repositories\Interfaces\PaymentRepositoryInterface::class],
            \App\Models\Entities\Currency::class              => [\App\Repositories\Interfaces\CurrencyRepositoryInterface::class],
            \App\Models\Entities\Language::class              => [\App\Repositories\Interfaces\LanguageRepositoryInterface::class],
            \App\Models\Entities\Coupon::class                => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\CouponProduct::class         => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\CouponCategory::class        => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\Gift::class                  => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\GiftItem::class              => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\GiftTriggerProduct::class    => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\Zone::class                  => [\App\Repositories\Interfaces\ZoneRepositoryInterface::class],
            \App\Models\Entities\Menu::class                  => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],
            \App\Models\Entities\MenuValue::class             => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],
            \App\Models\Entities\StoreReview::class           => [\App\Repositories\Interfaces\StoreReviewRepositoryInterface::class],
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
        if (! getSystemConfig('sql_log')) {
            return;
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
        $path = static::repositoryCachePath();
        $bindings = is_file($path) ? require $path : static::discoverRepositoryBindings();

        foreach ($bindings as $interface => $implementation) {
            $this->app->scoped($interface, $implementation);
        }
    }

    public static function repositoryCachePath(): string
    {
        return base_path('bootstrap/cache/repositories.php');
    }

    public static function discoverRepositoryBindings(): array
    {
        $dir = app_path('Repositories/Interfaces');
        if (! File::isDirectory($dir)) {
            return [];
        }

        $bindings = [];
        foreach (File::allFiles($dir) as $file) {
            $interface = 'App\\Repositories\\Interfaces\\' . $file->getBasename('.php');
            if (Str::contains($interface, 'BaseRepository')) {
                continue;
            }

            $implementation = Str::replaceFirst(
                'Interface',
                '',
                Str::replaceFirst('Interfaces', 'Eloquent', $interface),
            );

            if (interface_exists($interface) && class_exists($implementation)) {
                $bindings[$interface] = $implementation;
            }
        }

        return $bindings;
    }
}
