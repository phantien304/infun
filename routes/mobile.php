<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API v1 — APP KHÁCH HÀNG (model Member).
|--------------------------------------------------------------------------
| RouteServiceProvider::mapMobileRoutes() đã prefix sẵn 'api/v1'
| → URL cuối là /api/v1/*.  Tách hẳn khỏi CMS admin (/rcms).
|
| Auth: token Member có ability 'mobile'. Route cần đăng nhập dùng
| middleware 'auth:sanctum' + 'abilities:mobile'.
|
| Versioning: giữ ở /api/v1. Khi cần đổi API mà không phá app đã phát hành,
| tạo routes/mobile_v2.php + prefix 'api/v2' — app cũ vẫn chạy v1 bình thường.
*/

// ---- Public ----
Route::post('login', [AuthController::class, 'login']);
// Route::post('register', [AuthController::class, 'register']);            // TODO
// Route::post('forgot-password', [AuthController::class, 'forgotPassword']); // TODO

// ---- Cần token Member (ability 'mobile') ----
Route::middleware(['auth:sanctum', 'abilities:mobile'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // TODO: endpoint cho khách hàng (sản phẩm, giỏ hàng, đơn hàng, hồ sơ...).
});
