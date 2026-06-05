<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use File;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
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
     * Đăng ký observer — Review observer cập nhật aggregate cache trên product
     * (review_count, rating_avg, rating_sum, rating_distribution) khi review
     * đổi status approved hoặc rating value.
     */
    protected function registerObservers(): void
    {
        \App\Models\Entities\Review::observe(\App\Observers\ReviewObserver::class);
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
