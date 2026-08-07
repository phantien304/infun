<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Entities\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return respondUnprocessable($validator->errors()->first());
        }

        $user = User::query()
            ->where('email', (string) $request->input('email'))
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return respondUnprocessable(trans('messages.auth.login_failed'));
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return respondSuccess([
            'account'     => $this->accountPayload($user),
            'permissions' => $this->resolvePermissions($user),
        ], 'login_success');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return respondSuccess([
            'account'     => $this->accountPayload($user),
            'permissions' => $this->resolvePermissions($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return respondMessage('logout_success');
    }

    private function accountPayload(User $user): array
    {
        return [
            'id'       => $user->id,
            'name'     => $user->full_name ?? $user->username ?? $user->email,
            'username' => $user->username ?? null,
            'email'    => $user->email,
            'avatar'   => $user->avatar ?? null,
        ];
    }

    private function resolvePermissions(User $user): array
    {
        if ($user->hasRole(\App\Services\Cms\RoleWriteService::SUPER_ADMIN_ROLE, 'web')) {
            return \App\Enums\CmsPermissionEntity::allPermissionCodes();
        }

        return $user->getAllPermissions()->pluck('name')->all();
    }
}
