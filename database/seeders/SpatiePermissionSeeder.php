<?php

namespace Database\Seeders;

use App\Models\Entities\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Nạp dữ liệu demo cho spatie/laravel-permission.
 * -----------------------------------------------------------
 *  1. Import toàn bộ mã quyền cũ (bảng legacy 'permissions') sang bảng spatie
 *     (đã đổi tên thành sp_* trong config để không đụng bảng cũ).
 *  2. Tạo role 'admin' có tất cả quyền.
 *  3. Gán role 'admin' cho user đầu tiên để test ngay.
 *
 * Chạy:  php artisan db:seed --class=Database\\Seeders\\SpatiePermissionSeeder
 * -----------------------------------------------------------
 */
class SpatiePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        // Xoá cache quyền của spatie trước khi seed.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1) Import mã quyền từ bảng cũ 'permissions' (cột name = list-role, ...).
        $codes = DB::table('permissions')
            ->whereNotNull('name')
            ->pluck('name')
            ->filter()
            ->unique();

        foreach ($codes as $code) {
            Permission::findOrCreate($code, $guard);
        }

        // 2) Role admin = tất cả quyền.
        $admin = Role::findOrCreate('admin', $guard);
        $admin->syncPermissions(Permission::all());

        // 3) Gán cho user đầu tiên (demo).
        $user = User::query()->first();
        if ($user) {
            $user->assignRole($admin);
        }

        $this->command?->info('Spatie: imported ' . $codes->count() . ' permissions, role "admin" assigned to user #' . ($user->id ?? 'N/A'));
    }
}
