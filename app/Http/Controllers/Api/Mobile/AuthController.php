<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Entities\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            return respondUnprocessable($validator->errors()->first());
        }

        $member = Member::query()
            ->where('email', (string) $request->input('email'))
            ->first();

        if (! $member || ! Hash::check((string) $request->input('password'), (string) $member->password)) {
            return respondUnprocessable(trans('messages.auth.login_failed'));
        }

        $token = $member->createToken(self::TOKEN_NAME, ['mobile'])->plainTextToken;

        return respondSuccess([
                'token'   => $token,
                'account' => $this->accountPayload($member),
            ], 'login_success');
    }

    public function me(Request $request): JsonResponse
    {
        return respondSuccess(['account' => $this->accountPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return respondAccepted($message = 'logout_success');
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
}
