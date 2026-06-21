<?php

use App\Http\Controllers\Api\Cms\AuthController;
use App\Http\Controllers\Api\Cms\CategoryController;
use App\Http\Controllers\Api\Cms\SystemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — area = api (RouteServiceProvider::mapApiWebRoutes đã prefix
| sẵn '/rcms'). Các route bên dưới → URL cuối là /rcms/*.
|--------------------------------------------------------------------------
| SPA infun_cms set VITE_API_BASE_URL = http://<host>/rcms
| → http({ url: '/login' }) gọi tới POST /rcms/login.
*/

// Public — SPA cần init TRƯỚC khi login nên để ngoài auth:sanctum.
Route::post('login', [AuthController::class, 'login']);
Route::get('system/init', [SystemController::class, 'init']);

// Cần Bearer token (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // --- Category: REST chuẩn + spatie (apiResource + restore + bulk + tự phân quyền) ---
    // 1 dòng = list/store/show/update/destroy + restore + bulk, kèm middleware
    // cms.permission tự map action → quyền spatie ({list,detail,create,edit,del}-category).
    Route::cmsApiResource('category', CategoryController::class);

    // Entity REST khác: chỉ cần controller extends BaseCmsController + đặt $permission.
    // Route::cmsApiResource('product', ProductController::class);
});
