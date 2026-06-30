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
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $user = User::query()
            ->where('email', (string) $request->input('email'))
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return response()->json(['message' => trans('messages.auth.login_failed')], 422);
        }

        $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        $token = $user->createToken(self::TOKEN_NAME, ['cms'])->plainTextToken;

        // Contract REST thống nhất: { data, message }.
        return response()->json([
            'data' => [
                'token'       => $token,
                'account'     => $this->accountPayload($user),
                'permissions' => $this->resolvePermissions($user),
            ],
            'message' => 'login_success',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'account'     => $this->accountPayload($user),
                'permissions' => $this->resolvePermissions($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'logout_success']);
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
        // spatie: gộp quyền trực tiếp + quyền qua role → mảng mã ('list-category'...).
        // Frontend dùng để ẩn/hiện nút theo quyền.
        return $user->getAllPermissions()->pluck('name')->all();
    }
}
