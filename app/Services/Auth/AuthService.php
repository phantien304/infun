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
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $userRepo,
        protected UserPhoneRepositoryInterface $phoneRepo,
        protected UserResetPasswordRepositoryInterface $resetRepo,
        protected WishlistService $wishlistService,
    ) {
    }

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

    public function register(array $data): User
    {
        $email = (string) $data['email'];
        $code = $this->generateToken();

        $user = $this->userRepo->transaction(function () use ($data, $email, $code) {
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

        dispatch(new VerifyEmailJob($code, $email));

        return $user;
    }

    public function handleSocialUser(string $provider, object $socialUser): User
    {
        $email = (string) $socialUser->getEmail();
        $user = $this->userRepo->findByEmail($email);

        if (! $user) {
            $user = $this->userRepo->transaction(fn () => $this->userRepo->createUser([
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

    public function isResetTokenValid(string $code, string $email): bool
    {
        return filled($email) && $this->resetRepo->isValidCode($email, $code);
    }

    public function resetPassword(string $email, string $password): ?User
    {
        $user = $this->userRepo->transaction(fn () => $this->userRepo->updatePasswordByEmail($email, $password));
        if ($user) {
            $this->resetRepo->deleteForEmail($email);
        }

        return $user;
    }

    // ===== Verify email ===============================================

    public function verifyEmail(string $code, string $email): bool
    {
        $user = $this->userRepo->findByConfirmCode($code, $email);
        if (! $user) {
            return false;
        }
        if ((int) $user->confirmed === 1) {
            return true;
        }

        $this->userRepo->transaction(fn () => $this->userRepo->markConfirmed($user));
        dispatch(new AuthenticatedEmailJob($email));

        return true;
    }

    // ===== Helpers =====================================================

    public function decodeToken(?string $token): array
    {
        $parts = explode('+', base64_decode((string) $token), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    protected function generateToken(): string
    {
        return time() . uniqid('', true);
    }

    protected function afterAuthenticated(): void
    {
        $this->wishlistService->refreshSessionCounter();
    }
}
