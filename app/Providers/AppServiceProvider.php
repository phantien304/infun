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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerViewNamespaces();
        $this->logSql();
        $this->registerObservers();
    }

    /**
     * Đăng ký Blade view namespace area-first:
     *  - `web::layouts.main` → `resources/web/views/layouts/main.blade.php`
     *  - `cms::posts.list`   → `resources/cms/views/posts/list.blade.php`
     *
     * Cấu trúc resources/{area}/{type} mirror public/{area}/{type} convention
     * (vd public/web/css, resources/web/css). Namespace syntax `web::` /
     * `cms::` thay cho dot-prefix cũ `web.` / `cms.` — Laravel 11 standard.
     *
     * Sau khi đăng ký, mọi caller phải dùng `web::xxx` / `cms::xxx`. Dot-prefix
     * cũ KHÔNG còn resolve (đường path `resources/views/web/` đã bị move ra).
     */
    protected function registerViewNamespaces(): void
    {
        View::addNamespace('web', resource_path('web/views'));
        View::addNamespace('cms', resource_path('cms/views'));
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

        // Recompute denormalized aggregate (min/max_variant_price +
        // max_variant_discount_percent) trên bảng product mỗi khi variant
        // hoặc variant_special đổi. Co-exist với CacheFlushObserver bên
        // dưới — Laravel chạy cả 2 observer cho cùng model.
        \App\Models\Entities\ProductVariant::observe(\App\Observers\ProductVariantAggregateObserver::class);
        \App\Models\Entities\ProductVariantSpecial::observe(\App\Observers\ProductVariantAggregateObserver::class);

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

            // Coupon cluster — admin sửa coupon hoặc pivot SP/category → flush
            // listActiveForUser cache. UserCoupon (save/unsave) KHÔNG cần
            // cross-flush vì listSavedByUser không cache. CouponHistory cũng
            // không cache (đếm trực tiếp mỗi quota check).
            \App\Models\Entities\Coupon::class         => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\CouponProduct::class  => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],
            \App\Models\Entities\CouponCategory::class => [\App\Repositories\Interfaces\CouponRepositoryInterface::class],

            // Gift cluster — Gift / GiftItem / GiftTriggerProduct save → flush
            // listActive cache. OrderGift KHÔNG vào cacheMap vì là audit log
            // append-only, không ảnh hưởng list.
            \App\Models\Entities\Gift::class                => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\GiftItem::class            => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],
            \App\Models\Entities\GiftTriggerProduct::class  => [\App\Repositories\Interfaces\GiftRepositoryInterface::class],

            // Geography.
            \App\Models\Entities\Zone::class => [\App\Repositories\Interfaces\ZoneRepositoryInterface::class],

            // Menu — cả Menu (root) và MenuValue (leaf) cùng invalidate HTML
            // tree pre-rendered ở MenusClient.
            \App\Models\Entities\Menu::class      => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],
            \App\Models\Entities\MenuValue::class => [\App\Repositories\Interfaces\MenuRepositoryInterface::class],

            // Store review.
            \App\Models\Entities\StoreReview::class => [\App\Repositories\Interfaces\StoreReviewRepositoryInterface::class],
        ];

        // KHÔNG dùng `Model::observe(new CacheFlushObserver(...))` — Laravel
        // resolve observer by class name khi event fire, không lưu instance →
        // container resolve fresh → fail Unresolvable dependency `array
        // $repoInterfaces`. Dùng Event::listen với closure capture instance
        // để bypass container resolve.
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
