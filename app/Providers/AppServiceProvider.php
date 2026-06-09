<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->logSql();
        $this->registerObservers();
    }

    /**
     * Đăng ký observer cho 2 mục đích:
     *
     *  1. Custom observers (model có logic riêng):
     *     - `ReviewObserver` — cập nhật aggregate (review_count, rating_avg,
     *       rating_sum, rating_distribution) trên Product + invalidate cache.
     *     - `SettingObserver` — clear ConfigDbService cache + nếu key thuộc 3
     *       cờ cache flag thì gọi `CacheGate::flushAll()` wipe redis.
     *
     *  2. Cache invalidation generic: bảng mapping model → list repo interface
     *     ở `$cacheMap` dưới. `CacheFlushObserver` (1 file generic) gọi
     *     `flushCache()` của mỗi repo trong list khi model save/delete/restore.
     *
     *  Cross-flush (vd Category đổi → cả `categories` cache LẪN `product` cache):
     *  khai báo nhiều interface trong mảng, KHÔNG cần subclass observer riêng.
     *
     *  Khi thêm cache mới, BẮT BUỘC bổ sung entry vào `$cacheMap` — model
     *  thiếu = cache leak.
     */
    protected function registerObservers(): void
    {
        // === Custom observers (logic riêng, không thuộc generic) ===
        \App\Models\Entities\Review::observe(\App\Observers\ReviewObserver::class);
        \App\Models\Entities\Setting::observe(\App\Observers\SettingObserver::class);

        // === Generic cache invalidation ===
        // Map: model => [repoInterface, ...]. Mỗi repo trong list sẽ được
        // flushCache() khi model save/delete/restore. List >= 2 phần tử =
        // cross-flush (vd taxonomy đổi name → product card cũng stale).
        $cacheMap = [
            // Product cluster — mọi model con đổi đều flush tag product_root.
            \App\Models\Entities\Product::class               => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductSpecial::class        => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductVariant::class        => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductVariantSpecial::class => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductImage::class          => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\ProductCategory::class       => [\App\Repositories\Interfaces\ProductRepositoryInterface::class],

            // Taxonomy + cross-flush product cache (name hiển thị trên product card).
            \App\Models\Entities\Category::class     => [\App\Repositories\Interfaces\CategoryRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Manufacturer::class => [\App\Repositories\Interfaces\ManufacturerRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\Filter::class       => [\App\Repositories\Interfaces\FilterRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],
            \App\Models\Entities\FilterValue::class  => [\App\Repositories\Interfaces\FilterRepositoryInterface::class, \App\Repositories\Interfaces\ProductRepositoryInterface::class],

            // Blog cluster — không cross-flush vì product card không đụng tới.
            \App\Models\Entities\BlogCategory::class => [\App\Repositories\Interfaces\BlogCategoryRepositoryInterface::class],
            \App\Models\Entities\BlogTag::class      => [\App\Repositories\Interfaces\BlogTagRepositoryInterface::class],

            // Checkout static config — chỉ load ở trang checkout.
            \App\Models\Entities\Carrier::class => [\App\Repositories\Interfaces\CarrierRepositoryInterface::class],
            \App\Models\Entities\Payment::class => [\App\Repositories\Interfaces\PaymentRepositoryInterface::class],

            // Geography.
            \App\Models\Entities\Zone::class => [\App\Repositories\Interfaces\ZoneRepositoryInterface::class],

            // Menu — cả Menu (root) và MenuValue (leaf) cùng invalidate HTML
            // tree pre-rendered ở MenusClient.
            \App\Models\Entities\Menu::class      => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],
            \App\Models\Entities\MenuValue::class => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],

            // Store review.
            \App\Models\Entities\StoreReview::class => [\App\Repositories\Interfaces\StoreReviewRepositoryInterface::class],
        ];

        foreach ($cacheMap as $modelClass => $repoInterfaces) {
            $modelClass::observe(new \App\Observers\CacheFlushObserver($repoInterfaces));
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
