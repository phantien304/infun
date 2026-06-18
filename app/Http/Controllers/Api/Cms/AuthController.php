<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Entities\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * MẪU — API auth cho CMS SPA (React standalone `infun_cms`).
 *
 * Cơ chế: Sanctum personal access token (Bearer), stateless — KHÔNG dùng
 * session/cookie/CSRF. SPA lưu token vào localStorage (AUTH_TOKEN_CMS) và
 * gắn header `Authorization: Bearer <token>` cho mọi request sau đó.
 *
 * Hợp đồng response giữ theo convention dự án (helper trong Common.php):
 *   - thành công  : successData($msg, $data)   → { success:true, data:{...} }
 *   - lỗi         : errValidator($msg, 422)     → { success:false, message }
 * Frontend (http.js) đọc `response.data` của body → nhận thẳng object trong `data`.
 *
 * Endpoints (đăng ký ở routes/api.php, area=api → prefix /api, thêm /cms):
 *   POST /api/cms/login    (public)
 *   GET  /api/cms/me       (auth:sanctum)
 *   POST /api/cms/logout   (auth:sanctum)
 */
class AuthController extends Controller
{
    /** Tên token Sanctum cho phiên CMS — dùng để revoke đúng nhóm. */
    private const TOKEN_NAME = 'cms';

    /**
     * Đăng nhập: xác thực email + password, phát Bearer token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return errValidator($validator->errors()->first());
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('email', (string) $request->input('email'))
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return errValidator(trans('messages.auth.login_failed'));
        }

        // Tuỳ chính sách — chặn tài khoản bị khoá (bỏ comment nếu bảng có cột status):
        // if ((int) $user->status !== 1) {
        //     return errValidator(trans('messages.auth.account_disabled'));
        // }

        // 1 phiên CMS / user: revoke token CMS cũ trước khi phát token mới.
        // Bỏ dòng này nếu muốn cho phép đăng nhập nhiều thiết bị cùng lúc.
        $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        $token = $user->createToken(self::TOKEN_NAME, ['cms'])->plainTextToken;

        return successData('login_success', [
            'token'       => $token,
            'account'     => $this->accountPayload($user),
            'permissions' => $this->resolvePermissions($user),
        ]);
    }

    /**
     * Thông tin user hiện tại (SPA gọi để refresh account/permissions sau F5).
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return successData('', [
            'account'     => $this->accountPayload($user),
            'permissions' => $this->resolvePermissions($user),
        ]);
    }

    /**
     * Đăng xuất: xoá đúng token Bearer đang dùng (không đụng phiên khác).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return successNoData('logout_success');
    }

    /**
     * Payload account trả về cho SPA (lưu vào localStorage ACCOUNT_CMS).
     * Username là cột optional — Eloquent trả null nếu bảng không có.
     */
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

    /**
     * TODO: map quyền thật từ role của hệ thống. Tạm gom code quyền từ
     * quan hệ roleUsers (belongsToMany role_user). Trả mảng string để SPA
     * lưu PERMISSIONS_CMS và gate menu/route.
     */
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
