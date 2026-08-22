<?php

use App\Http\Controllers\Api\Cms\AuthController;
use App\Http\Controllers\Api\Cms\BannerController;
use App\Http\Controllers\Api\Cms\BlogCategoryController;
use App\Http\Controllers\Api\Cms\BlogController;
use App\Http\Controllers\Api\Cms\CarrierController;
use App\Http\Controllers\Api\Cms\Catalog\AttributeController;
use App\Http\Controllers\Api\Cms\Catalog\EffectController;
use App\Http\Controllers\Api\Cms\Catalog\FilterController;
use App\Http\Controllers\Api\Cms\Catalog\IngredientController;
use App\Http\Controllers\Api\Cms\Catalog\ManufacturerController;
use App\Http\Controllers\Api\Cms\Catalog\OptionController;
use App\Http\Controllers\Api\Cms\Catalog\SafetyController;
use App\Http\Controllers\Api\Cms\Catalog\SkincareController;
use App\Http\Controllers\Api\Cms\CategoryController;
use App\Http\Controllers\Api\Cms\Customer\ContactController;
use App\Http\Controllers\Api\Cms\CustomerController;
use App\Http\Controllers\Api\Cms\DistrictController;
use App\Http\Controllers\Api\Cms\FileController;
use App\Http\Controllers\Api\Cms\InformationController;
use App\Http\Controllers\Api\Cms\Marketing\CouponController;
use App\Http\Controllers\Api\Cms\Marketing\GiftController;
use App\Http\Controllers\Api\Cms\Marketing\MailCampaignController;
use App\Http\Controllers\Api\Cms\Marketing\VoucherController;
use App\Http\Controllers\Api\Cms\Marketing\VoucherRewardRuleController;
use App\Http\Controllers\Api\Cms\Marketing\VoucherThemeController;
use App\Http\Controllers\Api\Cms\MenuController;
use App\Http\Controllers\Api\Cms\MenuValueController;
use App\Http\Controllers\Api\Cms\Order\OrderController;
use App\Http\Controllers\Api\Cms\OrderStatusController;
use App\Http\Controllers\Api\Cms\PaymentController;
use App\Http\Controllers\Api\Cms\ProductController;
use App\Http\Controllers\Api\Cms\ResourceController;
use App\Http\Controllers\Api\Cms\Review\ReviewController;
use App\Http\Controllers\Api\Cms\Review\ReviewCriteriaController;
use App\Http\Controllers\Api\Cms\Review\ReviewTagController;
use App\Http\Controllers\Api\Cms\SettingController;
use App\Http\Controllers\Api\Cms\StoreReviewController;
use App\Http\Controllers\Api\Cms\System\RoleController;
use App\Http\Controllers\Api\Cms\System\UserGroupController;
use App\Http\Controllers\Api\Cms\SystemController;
use App\Http\Controllers\Api\Cms\UserController;
use App\Http\Controllers\Api\Cms\WardController;
use App\Http\Controllers\Api\Cms\WarehouseController;
use App\Http\Controllers\Api\Cms\ZoneController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::get('system/init', [SystemController::class, 'init']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('resource', [ResourceController::class, 'index']);
    Route::post('file/upload', [FileController::class, 'upload']);
    Route::post('file/upload-video', [FileController::class, 'uploadVideo']);
    Route::middleware('cms.permission')->group(function () {
        Route::cmsApiResource('category', CategoryController::class);
        Route::cmsApiResource('filter', FilterController::class);
        Route::cmsApiResource('attribute', AttributeController::class);
        Route::cmsApiResource('option', OptionController::class);
        Route::cmsApiResource('manufacturer', ManufacturerController::class);
        Route::cmsApiResource('ingredient', IngredientController::class);
        Route::get('effect', [EffectController::class, 'index']);
        Route::get('safety', [SafetyController::class, 'index']);
        Route::get('skincare', [SkincareController::class, 'index']);
        Route::get('contact', [ContactController::class, 'index']);
        Route::get('contact/{id}', [ContactController::class, 'show']);
        Route::delete('contact/{contact}', [ContactController::class, 'destroy']);
        Route::patch('contact/{id}/restore', [ContactController::class, 'restore']);
        Route::post('contact/bulk', [ContactController::class, 'bulk']);
        Route::cmsApiResource('banner', BannerController::class);

        // --- Marketing: coupon / voucher / quà tặng / thưởng voucher ---
        // `voucher/send` khai TRƯỚC apiResource cho dễ đọc (không xung đột
        // path, nhưng để sau thì trông như nằm ngoài nhóm). Quyền đã do
        // middleware 'cms.permission' của group này gác — CmsPermission::MAP
        // ánh xạ action 'send' → 'edit-voucher'.
        Route::post('voucher/send', [VoucherController::class, 'send']);
        Route::cmsApiResource('coupon', CouponController::class);
        Route::cmsApiResource('voucher', VoucherController::class);
        Route::cmsApiResource('voucher-theme', VoucherThemeController::class);
        Route::cmsApiResource('gift', GiftController::class);
        Route::cmsApiResource('voucher-reward-rule', VoucherRewardRuleController::class);
        // Mail marketing: chiến dịch đã gửi là BIÊN BẢN — chỉ xem và tạo,
        // không sửa/xoá, nên khai tay 3 route thay vì cmsApiResource.
        Route::get('mail-campaign', [MailCampaignController::class, 'index']);
        Route::post('mail-campaign', [MailCampaignController::class, 'store']);
        Route::get('mail-campaign/{id}', [MailCampaignController::class, 'show']);
        Route::cmsApiResource('store-review', StoreReviewController::class);
        Route::cmsApiResource('review-criteria', ReviewCriteriaController::class);
        Route::cmsApiResource('review-tag', ReviewTagController::class);
        // Review: khách gửi thì không sửa nội dung (chỉ đổi status) — nhưng
        // admin ĐƯỢC tạo review "mồi" (seed) cho sản phẩm mới qua store().
        Route::get('review', [ReviewController::class, 'index']);
        Route::post('review', [ReviewController::class, 'store']);
        Route::get('review/{id}', [ReviewController::class, 'show']);
        Route::put('review/{review}', [ReviewController::class, 'update']);
        Route::patch('review/{review}', [ReviewController::class, 'update']);
        Route::delete('review/{review}', [ReviewController::class, 'destroy']);
        Route::patch('review/{id}/restore', [ReviewController::class, 'restore']);
        Route::post('review/bulk', [ReviewController::class, 'bulk']);
        Route::post('review/{review}/reply', [ReviewController::class, 'reply']);
        Route::patch('review/{id}/report/{reportId}/resolve', [ReviewController::class, 'resolveReport']);
        Route::post('product/bulk-update', [ProductController::class, 'bulkUpdate']);
        Route::post('product/{id}/approve', [ProductController::class, 'approve']);
        Route::cmsApiResource('product', ProductController::class);
        Route::cmsApiResource('warehouse', WarehouseController::class);
        Route::cmsApiResource('order', OrderController::class);
        Route::cmsApiResource('blog', BlogController::class);
        Route::get('blog-category', [BlogCategoryController::class, 'index']);
        Route::cmsApiResource('menu', MenuController::class);
        Route::get('menu-value', [MenuValueController::class, 'index']);
        Route::get('menu-value/{menuValue}', [MenuValueController::class, 'show']);
        Route::post('menu-value', [MenuValueController::class, 'store']);
        Route::put('menu-value/{menuValue}', [MenuValueController::class, 'update']);
        Route::patch('menu-value/{menuValue}/rename', [MenuValueController::class, 'rename']);
        Route::delete('menu-value/{menuValue}', [MenuValueController::class, 'destroy']);
        Route::post('menu/{menuId}/values/reorder', [MenuValueController::class, 'reorder']);
        Route::post('menu/{menuId}/values/import-category', [MenuValueController::class, 'importCategory']);
        Route::delete('menu/{menuId}/values/categories', [MenuValueController::class, 'deleteCategories']);
        Route::cmsApiResource('information', InformationController::class);
        Route::post('order/preview-total', [OrderController::class, 'previewTotal']);
        Route::get('order-status', [OrderStatusController::class, 'index']);
        Route::get('carrier', [CarrierController::class, 'index']);
        Route::get('payment', [PaymentController::class, 'index']);
        Route::get('zone', [ZoneController::class, 'index']);
        Route::get('district', [DistrictController::class, 'index']);
        Route::get('ward', [WardController::class, 'index']);
        Route::get('role/permissions/registry', [RoleController::class, 'permissionsRegistry']);
        Route::apiResource('role', RoleController::class);
        Route::get('user', [UserController::class, 'index']);
        Route::cmsApiResource('user-group', UserGroupController::class);
        Route::cmsApiResource('user-group', UserGroupController::class);
        Route::get('user/admin-list', [UserController::class, 'adminList']);
        Route::patch('user/{id}/restore', [UserController::class, 'restore']);
        Route::post('user/bulk', [UserController::class, 'bulk']);
        Route::apiResource('user', UserController::class)->except(['index'])->withTrashed();
        Route::patch('customer/{id}/restore', [CustomerController::class, 'restore']);
        Route::post('customer/bulk', [CustomerController::class, 'bulk']);
        Route::apiResource('customer', CustomerController::class)->withTrashed();
        Route::get('setting', [SettingController::class, 'show']);
        Route::put('setting', [SettingController::class, 'update']);
        Route::post('setting/del', [SettingController::class, 'clearCache']);
    });
});
