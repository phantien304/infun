<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use App\Helpers\Facades\ExtendedRoute as Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $module = '';

    protected $namespace = 'App\Http\Controllers';

    public const HOME = '/home';

    public function boot()
    {
        parent::boot();
        if (!$this->app->routesAreCached()) {
            $this->map();
        }
    }

    public function map()
    {
        $this->mapCmsApiRoutes();

        $this->mapMobileRoutes();

        $this->mapCmsRoutes();

        $this->mapWebRoutes();
    }

    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace . '\Web')
            ->area('web')
            ->group(base_path('routes/web.php'));
    }

    protected function mapMobileRoutes()
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->area('api')
            ->namespace($this->namespace . '\Api\Mobile')
            ->group(base_path('routes/mobile.php'));
    }

    protected function mapCmsRoutes()
    {
        Route::middleware('cms')
            ->prefix('vcms')
            ->namespace($this->namespace . '\Cms')
            ->area('cms')
            ->group(base_path('routes/cms.php'));
    }

    protected function mapCmsApiRoutes()
    {
        Route::middleware('api')
            ->prefix('rcms')
            ->area('rcms')
            ->namespace($this->namespace . '\Api')
            ->group(base_path('routes/rcms.php'));
    }
}
