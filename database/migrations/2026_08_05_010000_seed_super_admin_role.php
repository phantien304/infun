<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed role hệ thống "Super Admin" (docs/ROLE-PERMISSION-PLAN.md mục Super
 * Admin, thêm 2026-08-05) — cùng cách làm với các migration seed_*_cms_permissions
 * (raw DB::table, không dùng Eloquent Role, tránh phụ thuộc autoload lúc
 * migrate sớm trong pipeline deploy).
 *
 * CHỈ tạo role RỖNG QUYỀN — Gate::before (AppServiceProvider::registerSuperAdminBypass)
 * bypass theo TÊN role, không theo nội dung sp_role_has_permissions, nên
 * KHÔNG cần (và KHÔNG NÊN) gán permission nào ở đây.
 *
 * KHÔNG gán role này cho user nào — đây là bootstrap phải làm THỦ CÔNG qua
 * tinker (chicken-and-egg giống hệt lần seed admin đầu tiên của hệ thống):
 *
 *   php artisan tinker
 *   >>> $user = \App\Models\Entities\User::where('email', 'admin@example.com')->first();
 *   >>> $user->assignRole('Super Admin');
 *
 * Idempotent (insertOrIgnore theo unique (name, guard_name) của sp_roles) —
 * an toàn chạy lại nhiều lần / nhiều môi trường.
 */
return new class () extends Migration {
    private const GUARD = 'web';

    private const ROLE_NAME = 'Super Admin';

    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles') ?: 'sp_roles';

        $now = now();
        DB::table($rolesTable)->insertOrIgnore([
            'name'       => self::ROLE_NAME,
            'guard_name' => self::GUARD,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->flushPermissionCache();
    }

    public function down(): void
    {
        // Intentionally KHÔNG xoá — rollback tự động xoá role "Super Admin"
        // có thể vô tình tước quyền của (những) tài khoản đang thật sự là
        // Super Admin nếu ai đó lỡ chạy rollback trên môi trường đã gán
        // thật. Xoá (nếu thật sự cần) phải làm thủ công + xác nhận rõ ràng,
        // giống nguyên tắc ở 2026_08_05_000000_drop_legacy_role_permission_tables.
    }

    private function flushPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
