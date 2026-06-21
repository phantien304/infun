<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Entities\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private const TOKEN_NAME = 'cms';

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return errValidator($validator->errors()->first());
        }

        $user = User::query()
            ->where('email', (string) $request->input('email'))
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return errValidator(trans('messages.auth.login_failed'));
        }

        $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        $token = $user->createToken(self::TOKEN_NAME, ['cms'])->plainTextToken;

        return successData('login_success', [
            'token'       => $token,
            'account'     => $this->accountPayload($user),
            'permissions' => $this->resolvePermissions($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return successData('', [
            'account'     => $this->accountPayload($user),
            'permissions' => $this->resolvePermissions($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return successNoData('logout_success');
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
        // Ví dụ khung (điều chỉnh theo schema role/permission thực tế):
        // return $user->roleUsers()
        //     ->with('permissions')
        //     ->get()
        //     ->flatMap(fn ($role) => $role->permissions->pluck('code'))
        //     ->unique()->values()->all();

        return [];
    }
}
