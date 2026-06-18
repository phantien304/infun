<?php

use App\Http\Controllers\Api\Cms\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — area = api (RouteServiceProvider::mapApiWebRoutes đã prefix
| sẵn '/api'). Nhóm 'cms' bên dưới → URL cuối là /api/cms/*.
|--------------------------------------------------------------------------
| SPA infun_cms set VITE_API_BASE_URL = http://<host>/api/cms
| → http({ url: '/login' }) gọi tới POST /api/cms/login.
*/
Route::prefix('cms')->group(function () {
    // Public
    Route::post('login', [AuthController::class, 'login']);

    // Cần Bearer token (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // TODO: thêm /system/init + CRUD module ở đây.
        // Route::get('system/init', [SystemController::class, 'init']);
    });
});
