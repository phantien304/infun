<?php

use App\Http\Controllers\Api\Cms\AuthController;
use App\Http\Controllers\Api\Cms\BannerController;
use App\Http\Controllers\Api\Cms\BlogCategoryController;
use App\Http\Controllers\Api\Cms\BlogController;
use App\Http\Controllers\Api\Cms\CarrierController;
use App\Http\Controllers\Api\Cms\CategoryController;
use App\Http\Controllers\Api\Cms\CustomerController;
use App\Http\Controllers\Api\Cms\DistrictController;
use App\Http\Controllers\Api\Cms\FileController;
use App\Http\Controllers\Api\Cms\InformationController;
use App\Http\Controllers\Api\Cms\MenuController;
use App\Http\Controllers\Api\Cms\MenuValueController;
use App\Http\Controllers\Api\Cms\Order\OrderController;
use App\Http\Controllers\Api\Cms\OrderStatusController;
use App\Http\Controllers\Api\Cms\PaymentController;
use App\Http\Controllers\Api\Cms\ProductController;
use App\Http\Controllers\Api\Cms\ResourceController;
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

    // --- Banner: REST chuẩn + spatie (giống Category) — 2 tầng con
    // banner_values[].banner_value_descriptions[] xử lý trong saveFromCms.
    Route::cmsApiResource('banner', BannerController::class);

    // --- StoreReview: REST chuẩn + spatie (giống Category) — convert từ
    // mt219 app/Http/Controllers/Cms/StoreReviewController.php (Blade cũ).
    // "Khách hàng của In&Fun" ở trang chủ (web::page.child.store_review).
    Route::cmsApiResource('store-review', StoreReviewController::class);

    // --- Product (đợt 1: read + delete; store/update ở đợt 2) ---
    Route::get('resource', [ResourceController::class, 'index']);          // dropdown cho form
    Route::post('product/bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::post('product/{id}/approve', [ProductController::class, 'approve']);
    Route::cmsApiResource('product', ProductController::class);

    // --- Blog: REST chuẩn + spatie (giống Category) ---
    Route::cmsApiResource('blog', BlogController::class);
    // Dropdown category cho blog/form.jsx (CHỈ read — CRUD blog-category chưa convert).
    Route::middleware('cms.permission')->group(function () {
        Route::get('blog-category', [BlogCategoryController::class, 'index']);
        // Autocomplete "Author" cho blog/form.jsx (CHỈ read — CRUD user chưa convert).
        Route::get('user', [UserController::class, 'index']);
    });

    // --- Menu: REST chuẩn + spatie (giống Category — KHÔNG có bảng dịch, chỉ title/position/theme) ---
    Route::cmsApiResource('menu', MenuController::class);

    // --- MenuValue: cây menu con của 1 Menu — permission RIÊNG 'menu-value'
    // (sp_permissions đã có sẵn list/detail/create/edit/del-menu-value).
    // KHÔNG dùng cmsApiResource (menu_value không soft-delete → không có
    // restore; có 3 action riêng ngoài REST chuẩn: reorder/import/xoá category).
    Route::middleware('cms.permission')->group(function () {
        Route::get('menu-value', [MenuValueController::class, 'index']);
        Route::get('menu-value/{menuValue}', [MenuValueController::class, 'show']);
        Route::post('menu-value', [MenuValueController::class, 'store']);
        Route::put('menu-value/{menuValue}', [MenuValueController::class, 'update']);
        Route::patch('menu-value/{menuValue}/rename', [MenuValueController::class, 'rename']);
        Route::delete('menu-value/{menuValue}', [MenuValueController::class, 'destroy']);

        // Kéo-thả sắp cây / Import từ Category / Xoá Category (menu/form.jsx).
        Route::post('menu/{menuId}/values/reorder', [MenuValueController::class, 'reorder']);
        Route::post('menu/{menuId}/values/import-category', [MenuValueController::class, 'importCategory']);
        Route::delete('menu/{menuId}/values/categories', [MenuValueController::class, 'deleteCategories']);

        // Dropdown target "Information" cho menu-value type=information (CHỈ read).
        Route::get('information', [InformationController::class, 'index']);
    });

    // --- Warehouse: REST chuẩn + spatie (giống Menu — quản lý danh sách kho
    // cho tính năng đa kho product_stock). Dropdown cho product/Option.jsx
    // đọc qua GET /rcms/resource?list_for_product=true (KHÔNG qua route này,
    // để không cần quyền 'list-warehouse' chỉ để sửa tồn kho sản phẩm).
    Route::cmsApiResource('warehouse', WarehouseController::class);

    // --- Order: REST chuẩn + spatie (giống Category/Product) + 1 action riêng ---
    // previewTotal KHÔNG ghi DB (tab Confirm gọi để xem trước tổng tiền) —
    // thêm map 'previewTotal' => 'detail' ở CmsPermission (coi như xem chi tiết).
    Route::middleware('cms.permission')->group(function () {
        Route::post('order/preview-total', [OrderController::class, 'previewTotal']);
    });
    Route::cmsApiResource('order', OrderController::class);

    // Dropdown cho order/form.jsx + order/view.jsx (CHỈ read — CRUD riêng của
    // từng entity này chưa convert, xem CRUD_ENTITIES phía infuncms).
    Route::middleware('cms.permission')->group(function () {
        Route::get('order-status', [OrderStatusController::class, 'index']);
        Route::get('carrier', [CarrierController::class, 'index']);
        Route::get('payment', [PaymentController::class, 'index']);
        Route::get('zone', [ZoneController::class, 'index']);
        Route::get('district', [DistrictController::class, 'index']);
        Route::get('ward', [WardController::class, 'index']);
    });

    // Upload ảnh (Photo.jsx) — Bearer token, thay cho '/vcms/file/save' cũ
    // không tồn tại. Xem App\Http\Controllers\Api\Cms\FileController.
    Route::post('file/upload', [FileController::class, 'upload']);
    // Upload video R2 cho banner_value dạng video (VideoUpload.jsx).
    Route::post('file/upload-video', [FileController::class, 'uploadVideo']);

    // --- System: UserGroup / Role / User admin (Phase 2, docs/ROLE-PERMISSION-PLAN.md) ---

    // UserGroup: REST chuẩn + spatie (giống Category — có soft delete + i18n).
    Route::cmsApiResource('user-group', UserGroupController::class);

    // Role (spatie): KHÔNG dùng cmsApiResource — Spatie\Permission\Models\Role
    // KHÔNG có deleted_at (xem migration create_permission_tables), không
    // withTrashed/restore được. 'permissions/registry' PHẢI đăng ký TRƯỚC
    // apiResource — nếu không, GET role/{role} (route show, wildcard) sẽ
    // nuốt mất "permissions" làm giá trị {role} (cùng lý do product/bulk-update
    // đứng trước cmsApiResource('product', ...) phía trên).
    Route::middleware('cms.permission')->group(function () {
        Route::get('role/permissions/registry', [RoleController::class, 'permissionsRegistry']);
    });
    Route::middleware('cms.permission')->group(function () {
        Route::apiResource('role', RoleController::class);
    });

    // User admin (type=1 — quyết định Phase 6.1 "chỉ Admin", KHÔNG đụng
    // Member type=2). 'admin-list' PHẢI đăng ký TRƯỚC apiResource cùng lý do
    // role/permissions/registry ở trên. 'index' loại khỏi apiResource vì
    // GET /rcms/user đã là route CŨ (dropdown Author cho blog/form.jsx,
    // đăng ký phía trên) — KHÔNG đổi hành vi route đó.
    Route::middleware('cms.permission')->group(function () {
        Route::get('user/admin-list', [UserController::class, 'adminList']);
    });
    Route::middleware('cms.permission')->group(function () {
        Route::patch('user/{id}/restore', [UserController::class, 'restore']);
        Route::post('user/bulk', [UserController::class, 'bulk']);
        Route::apiResource('user', UserController::class)->except(['index'])->withTrashed();
    });

    // Customer (type=2 — App\Enums\UserType::Member), theo yêu cầu user "Đầy
    // đủ CRUD như màn User admin" (docs/ROLE-PERMISSION-PLAN.md phần mở rộng
    // sau Phase 4). Route param {customer} nhưng model là App\Models\Entities\
    // User (CÙNG bảng `user`, khác permission slug 'customer' — xem
    // CustomerController docblock). KHÔNG cần route riêng như 'admin-list'
    // (Customer không có route dropdown cũ nào đụng path GET /rcms/customer).
    Route::middleware('cms.permission')->group(function () {
        Route::patch('customer/{id}/restore', [CustomerController::class, 'restore']);
        Route::post('customer/bulk', [CustomerController::class, 'bulk']);
        Route::apiResource('customer', CustomerController::class)->withTrashed();
    });

    // --- Setting: mt219 Vue2 (5 tab General/Store/Order/Seo/Footer) → React
    // setting/detail.jsx. Không dùng cmsApiResource (không phải resource CRUD
    // list — 1 form sửa nhiều key key-value cùng lúc).
    // BỌC cms.permission (đổi 2026-08-03, xem docs/ROLE-PERMISSION-PLAN.md
    // mục 0.2) — trước đây 'setting' nằm trong Permissions::$_excepts, mọi
    // tài khoản CMS đăng nhập được đều sửa được cấu hình (kể cả maintenance
    // mode, tỉ lệ hoa hồng affiliate...). clearCache KHÔNG có action tương
    // ứng trong CmsPermission::MAP nên tự động KHÔNG bị gác (giữ nguyên chủ
    // ý cũ: xoá cache không đổi dữ liệu nghiệp vụ, không cần quyền riêng).
    Route::middleware('cms.permission')->group(function () {
        Route::get('setting', [SettingController::class, 'show']);
        Route::put('setting', [SettingController::class, 'update']);
        // Giữ nguyên URL 'setting/del' + envelope {success,message,data} —
        // nút "Xoá cache" ở AppLayout.jsx đang gọi qua http.js (client
        // legacy), CHƯA migrate sang api.js. Đổi contract sẽ làm nút đó hỏng.
        Route::post('setting/del', [SettingController::class, 'clearCache']);
    });
});
