<?php

namespace App\Services\Auth;

use App\Jobs\AuthenticatedEmailJob;
use App\Jobs\ForgotPasswordJob;
use App\Jobs\VerifyEmailJob;
use App\Models\Entities\User;
use App\Repositories\Interfaces\UserPhoneRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\UserResetPasswordRepositoryInterface;
use App\Services\Account\WishlistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Logic xác thực cho frontend (luồng KHÁCH HÀNG, guard `web`, bảng `user`).
 *
 * Tách khỏi controller theo pattern Account (xem CLAUDE.md "Account flow"):
 *  - register / social = transaction nhiều bảng (user + user_phone).
 *  - reset password = upsert token + đổi hash, lock row chống race.
 *  - verify email = đổi cờ confirmed + dispatch mail đã kích hoạt.
 *
 * Controller chỉ điều phối request/redirect; mọi mutate đi qua service này.
 */
class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $userRepo,
        protected UserPhoneRepositoryInterface $phoneRepo,
        protected UserResetPasswordRepositoryInterface $resetRepo,
        protected WishlistService $wishlistService,
    ) {
    }

    // ===== Login =======================================================

    /**
     * Đăng nhập bằng email + password. Ràng buộc: đã xác thực email
     * (confirmed = 1), đang active (status = 1) và là khách hàng
     * (type = member) — chặn tài khoản admin login qua cổng frontend.
     */
    public function login(string $email, string $password, bool $remember = true): bool
    {
        $ok = Auth::attempt([
            'email'     => $email,
            'password'  => $password,
            'confirmed' => 1,
            'status'    => 1,
            'type'      => (int) getCoreConfig('user.type.member'),
        ], $remember);

        if ($ok) {
            $this->afterAuthenticated();
        }

        return $ok;
    }

    // ===== Register ====================================================

    /**
     * Tạo tài khoản khách hàng + bản ghi số điện thoại trong 1 transaction,
     * rồi gửi email xác thực. Mật khẩu hash bằng bcrypt. confirmed = 0 →
     * user phải kích hoạt qua link email trước khi login được.
     */
    public function register(array $data): User
    {
        $email = (string) $data['email'];
        $code = $this->generateToken();

        $user = DB::transaction(function () use ($data, $email, $code) {
            $user = $this->userRepo->createUser([
                'email'        => $email,
                'confirm_code' => $code,
                'full_name'    => (string) ($data['full_name'] ?? ''),
                'password'     => Hash::make((string) $data['password']),
                'type'         => (int) getCoreConfig('user.type.member'),
                'status'       => 1,
                'confirmed'    => 0,
            ]);

            $this->phoneRepo->upsertForUser((int) $user->id, [
                'nation_phone_code' => (string) ($data['nation_phone_code'] ?? ''),
                'phone'             => (string) ($data['phone'] ?? ''),
                'is_verify'         => 0,
            ]);

            return $user;
        });

        // Dispatch SAU commit — chỉ gửi mail khi user đã thực sự được lưu.
        dispatch(new VerifyEmailJob($code, $email));

        return $user;
    }

    // ===== Social login ===============================================

    /**
     * Đăng nhập / đăng ký qua provider (facebook, google...). Tìm theo email;
     * chưa có → tạo mới (confirmed = 1 vì provider đã xác thực email).
     * Sau đó login bằng id (bỏ qua ràng buộc password).
     *
     * @param  object  $socialUser  Laravel\Socialite\Contracts\User
     */
    public function handleSocialUser(string $provider, object $socialUser): User
    {
        $email = (string) $socialUser->getEmail();
        $user = $this->userRepo->findByEmail($email);

        if (! $user) {
            $user = DB::transaction(fn () => $this->userRepo->createUser([
                'full_name'     => (string) ($socialUser->getName() ?? ''),
                'email'         => $email,
                'avatar'        => (string) ($socialUser->getAvatar() ?? ''),
                'social_id'     => (string) ($socialUser->getId() ?? ''),
                'confirmed'     => 1,
                'status'        => 1,
                'type'          => (int) getCoreConfig('user.type.member'),
                'type_register' => $provider,
            ]));
        }

        Auth::loginUsingId($user->id, true);
        $this->afterAuthenticated();

        return $user;
    }

    // ===== Forgot / reset password ====================================

    /**
     * Tạo token reset + gửi link đổi mật khẩu. Trả false nếu email không
     * thuộc khách hàng nào (controller báo "email không tồn tại").
     */
    public function sendResetLink(string $email): bool
    {
        $user = $this->userRepo->findMemberByEmail($email);
        if (! $user) {
            return false;
        }

        $code = $this->generateToken();
        $this->resetRepo->upsertForEmail($email, $code);

        dispatch(new ForgotPasswordJob($code, $email));

        return true;
    }

    /** Token reset (email + code) còn hợp lệ? */
    public function isResetTokenValid(string $code, string $email): bool
    {
        return filled($email) && $this->resetRepo->isValidCode($email, $code);
    }

    /**
     * Đổi password theo email rồi xoá token (dùng 1 lần). Trả user đã đổi
     * hoặc null nếu không tìm thấy.
     */
    public function resetPassword(string $email, string $password): ?User
    {
        $user = DB::transaction(fn () => $this->userRepo->updatePasswordByEmail($email, $password));
        if ($user) {
            $this->resetRepo->deleteForEmail($email);
        }

        return $user;
    }

    // ===== Verify email ===============================================

    /**
     * Kích hoạt tài khoản qua link xác thực. Trả true nếu user tồn tại và
     * (đã / vừa) confirmed; false nếu token không khớp user nào.
     */
    public function verifyEmail(string $code, string $email): bool
    {
        $user = $this->userRepo->findByConfirmCode($code, $email);
        if (! $user) {
            return false;
        }
        if ((int) $user->confirmed === 1) {
            return true;
        }

        DB::transaction(fn () => $this->userRepo->markConfirmed($user));
        dispatch(new AuthenticatedEmailJob($email));

        return true;
    }

    // ===== Helpers =====================================================

    /**
     * Giải mã token `base64(code + '+' + email)` dùng cho verify email và
     * change password. Trả [code, email] — phần thiếu trả chuỗi rỗng.
     */
    public function decodeToken(?string $token): array
    {
        $parts = explode('+', base64_decode((string) $token));

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    protected function generateToken(): string
    {
        return time() . uniqid('', true);
    }

    /**
     * Side-effect sau khi đăng nhập thành công: đồng bộ badge wishlist trên
     * header cho user mới. Session đã được guard tự regenerate khi login.
     */
    protected function afterAuthenticated(): void
    {
        $this->wishlistService->refreshSessionCounter();
    }
}
