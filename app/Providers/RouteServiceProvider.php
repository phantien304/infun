<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use App\Helpers\Facades\ExtendedRoute as Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $module = '';
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        if (!$this->app->routesAreCached()) {
            $this->map();
        }
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiWebRoutes();

        $this->mapCmsRoutes();

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace . '\Web')
            ->area('web')
            ->group(base_path('routes/web.php'));
    }

    protected function mapCmsRoutes()
    {
        Route::middleware('cms')
            ->prefix('vcms')
            ->namespace($this->namespace . '\Cms')
            ->area('cms')
            ->group(base_path('routes/cms.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */

    protected function mapApiWebRoutes()
    {
        Route::middleware('api')
            ->prefix('api')
            ->area('api')
            ->namespace($this->namespace . '\Api')
            ->group(base_path('routes/api.php'));
    }
}
