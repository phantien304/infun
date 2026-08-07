<?php

namespace App\Http\Controllers\Api\Cms;

use App\Models\Entities\Setting;
use App\Repositories\Interfaces\SettingRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Setting API cho CMS — convert từ mt219 Cms\SettingController (Vue2 màn
 * "Cài đặt" 1 form nhiều tab: General/Store/Order/Seo/Footer).
 *
 * Khác mt219:
 *  - GET trả CẢ setting (không lọc cms_public — form CMS cần sửa mọi key,
 *    cms_public chỉ quyết định key nào lộ ra GET /system/init cho SPA client
 *    khác, xem Setting::casts + SystemController::init).
 *  - update() lưu qua Eloquent (KHÔNG dùng query builder mass update) để
 *    SettingObserver::saved() bắn cho TỪNG row — observer đó vừa flush cache
 *    (ConfigDbService) vừa xử lý CacheGate::flushAll() khi đổi 1 trong 3 cờ
 *    config_debug/config_redis_cache/config_cache_file. Mass update qua query
 *    builder sẽ bỏ qua toàn bộ logic này.
 *  - Không có route riêng để "tạo" hay "xoá" 1 setting key — bảng setting chỉ
 *    được seed qua migration (xem 2026_07_10_000000/000001, 2026_07_08_000002).
 *    update() CHỈ ghi đè key đã tồn tại, bỏ qua key lạ từ FE (giống mt219).
 *  - BỌC cms.permission (đổi 2026-08-03 — xem docs/ROLE-PERMISSION-PLAN.md
 *    mục 0.2): trước đây 'setting' nằm trong Permissions::$_excepts, nghĩa
 *    là MỌI tài khoản CMS đăng nhập được đều bật/tắt maintenance, đổi tỉ lệ
 *    hoa hồng affiliate, tỉ lệ quy đổi điểm thưởng — màn nhạy cảm nhất hệ
 *    thống mà lại không gác. Method GET đổi tên index()→show() để khớp map
 *    mặc định của CmsPermission (index→list, show→detail) — ra đúng quyền
 *    'detail-setting' thay vì 'list-setting' (setting là 1 object, không
 *    phải danh sách). Quyền 'detail-setting'/'edit-setting' seed ở migration
 *    kèm theo (xem database/migrations/*_seed_setting_cms_permissions.php).
 */
class SettingController extends BaseCmsController
{
    protected string $permission = 'setting';

    public function __construct(
        private readonly SettingRepositoryInterface $settingRepo,
    ) {
    }

    /** GET /rcms/setting — toàn bộ config (mọi key, kể cả cms_public=0) cho form CMS. */
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->settingRepo->listAllCached()]);
    }

    /**
     * PUT /rcms/setting — lưu hàng loạt key=>value cùng lúc (mirror mt219
     * SettingController@save: body phẳng {key: value, ...}, mảng → JSON +
     * serialized=1). Không có FormRequest riêng: bảng setting đang có ~35
     * key khác nhau (số, chuỗi, mảng, HTML) và tiếp tục tăng theo tính năng
     * mới (reward/affiliate...) — validate cứng từng key ở đây sẽ luôn lệch
     * pha với migration seed. Màn hình chỉ vào được sau auth:sanctum (admin).
     */
    public function update(Request $request): JsonResponse
    {
        $payload = $request->all();

        $rows = Setting::query()
            ->where('code', 'config')
            ->whereIn('key', array_keys($payload))
            ->get()
            ->keyBy('key');

        foreach ($payload as $key => $value) {
            $setting = $rows->get($key);
            if (! $setting) {
                continue; // key lạ (không có sẵn trong DB) — bỏ qua, không auto-create.
            }

            $serialized = 0;
            if (is_array($value)) {
                $serialized = 1;
                $value = json_encode($value);
            }

            // forceFill()->save() (KHÔNG phải query builder ->update(), KHÔNG
            // phải model ->update() thường) — 2 lý do:
            //   1. Cần bắn event 'saved' → SettingObserver flush cache. Query
            //      builder Setting::where(...)->update() (cách mt219 làm) KHÔNG
            //      bắn Eloquent event.
            //   2. Setting model KHÔNG khai $fillable (trước giờ chỉ bị ghi qua
            //      query builder/migration nên chưa ai cần) → model ->update()
            //      thường sẽ ăn MassAssignmentException. forceFill() bỏ qua
            //      guard nhưng vẫn fill() + save() bình thường (vẫn bắn event).
            $setting->forceFill([
                'value'      => $value,
                'serialized' => $serialized,
            ])->save();
        }

        return response()->json([
            'data'    => $this->settingRepo->listAllCached(),
            'message' => 'SaveSuccess',
        ]);
    }

    /**
     * POST /rcms/setting/del — "Xoá cache" (nút ở AppLayout.jsx topbar).
     * Giữ NGUYÊN contract envelope legacy {success,message,data} — AppLayout.jsx
     * gọi qua http.js (chưa migrate sang api.js), đổi shape ở đây sẽ làm nút đó
     * hỏng ngay. Logic y hệt mt219 SettingController@del.
     */
    public function clearCache(): JsonResponse
    {
        if (Cache::getDefaultDriver() === 'redis') {
            Cache::flush();
        }
        File::deleteDirectory(storage_path('framework/cache'));

        return response()->json([
            'success' => true,
            'message' => 'DeleteSuccess',
            'data'    => null,
        ]);
    }
}
