<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Entities\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * API xác thực cho APP KHÁCH HÀNG (Android/iOS).
 * -----------------------------------------------------------
 * Tách hẳn khỏi CMS admin:
 *   - CMS admin  → Api\Cms\AuthController, model User,   ability ['cms']
 *   - App khách  → file này,              model Member, ability ['mobile']
 *
 * Cùng đi qua guard 'auth:sanctum' nhưng phân tách bằng ability, nên token
 * của app này KHÔNG dùng được endpoint của app kia.
 * -----------------------------------------------------------
 */
class AuthController extends Controller
{
    private const TOKEN_NAME = 'mobile';

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $member = Member::query()
            ->where('email', (string) $request->input('email'))
            ->first();

        if (! $member || ! Hash::check((string) $request->input('password'), (string) $member->password)) {
            return response()->json(['message' => trans('messages.auth.login_failed')], 422);
        }

        // Cấp token mới mỗi lần đăng nhập, KHÔNG xoá token cũ → cho phép khách
        // hàng đăng nhập nhiều thiết bị. Ability ['mobile'] giới hạn phạm vi.
        $token = $member->createToken(self::TOKEN_NAME, ['mobile'])->plainTextToken;

        // Contract REST thống nhất: { data, message }.
        return response()->json([
            'data' => [
                'token'   => $token,
                'account' => $this->accountPayload($member),
            ],
            'message' => 'login_success',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['account' => $this->accountPayload($request->user())],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Chỉ thu hồi token của thiết bị hiện tại (đăng xuất 1 máy).
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'logout_success']);
    }

    private function accountPayload(Member $member): array
    {
        return [
            'id'     => $member->id,
            'name'   => $member->full_name ?? $member->email,
            'email'  => $member->email,
            'phone'  => $member->phone ?? null,
            'avatar' => $member->avatar ?? null,
        ];
    }

    // ----------------------------------------------------------------
    // TODO khi xây app khách hàng:
    //   public function register(Request $request): JsonResponse { /* tạo Member */ }
    //   public function forgotPassword(Request $request): JsonResponse { /* gửi mail reset */ }
    // ----------------------------------------------------------------
}
