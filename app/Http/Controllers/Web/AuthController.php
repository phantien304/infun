<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AuthForgotPasswordRequest;
use App\Http\Requests\Web\AuthLoginRequest;
use App\Http\Requests\Web\AuthRegisterRequest;
use App\Http\Requests\Web\AuthResetPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

/**
 * Xác thực frontend (luồng KHÁCH HÀNG): login / register / social login /
 * forgot + reset password / verify email.
 *
 * Refactor 2026-06-13 — đưa khỏi trạng thái legacy hỏng (namespace sai
 * `Client\InfunStudio`, extends `BaseInfunStudioController` không tồn tại,
 * dùng repo/job/validator legacy đã xoá). Chuẩn hoá theo pattern Account
 * (xem CLAUDE.md "Account flow"):
 *  - Namespace `App\Http\Controllers\Web`, extends base `Controller` mới.
 *  - Toàn bộ nghiệp vụ đẩy xuống `App\Services\Auth\AuthService`.
 *  - Validate qua FormRequest `App\Http\Requests\Web\Auth*Request`.
 *  - Route::any (GET render form + POST xử lý) → resolve FormRequest qua
 *    container TRONG nhánh POST (không type-hint ở signature để tránh chạy
 *    validation cả trên GET).
 *  - Helper mới: `route()` thay tự build URL, `processMetaSeo()` thay
 *    `_processMetaSeo()`, render view namespace `web::auth.*`.
 *
 * Lưu ý: các blade `web::auth.*` hiện vẫn ở trạng thái legacy (extends layout
 * cũ + thiếu @csrf) — sẽ migrate ở task riêng. Controller đã sẵn sàng.
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
        ];
    }

    // ===== Login =======================================================

    public function login(Request $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }
        if ($request->isMethod('post')) {
            return $this->handleLogin();
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.login'), 'href' => route('auth.login'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.login.title', 'auth.login.description');

        return $this->render('web::auth.login');
    }

    protected function handleLogin()
    {
        $data = app(AuthLoginRequest::class)->validated();

        $ok = $this->authService->login(
            (string) $data['email'],
            (string) $data['password'],
        );

        if (! $ok) {
            return redirect(route('auth.login'))
                ->with('failed', trans('messages.auth.login_failed'))
                ->withInput();
        }

        return redirect(route('account.index'));
    }

    // ===== Social login ===============================================

    public function loginWithProvider(string $provider)
    {
        try {
            $scopes = (array) config("services.{$provider}.scopes", []);
            $driver = Socialite::driver($provider);

            return $scopes ? $driver->scopes($scopes)->redirect() : $driver->redirect();
        } catch (\Throwable $e) {
            logError($e->getMessage());

            return redirect(route('auth.login'))
                ->with('failed', sprintf(trans('messages.LoginWithProviderFailed'), ucfirst($provider)));
        }
    }

    public function handleProviderCallback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            $this->authService->handleSocialUser($provider, $socialUser);

            return redirect(route('account.index'))
                ->with('success', sprintf(trans('messages.LoginWithProviderSuccess'), ucfirst($provider)));
        } catch (\Throwable $e) {
            logError($e->getMessage());

            return redirect(route('auth.login'))
                ->with('failed', sprintf(trans('messages.LoginWithProviderFailed'), ucfirst($provider)));
        }
    }

    // ===== Register ====================================================

    public function register(Request $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }
        if ($request->isMethod('post')) {
            return $this->handleRegister();
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.register'), 'href' => route('auth.register'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.register.title', 'auth.register.description');

        return $this->render('web::auth.register');
    }

    protected function handleRegister()
    {
        $data = app(AuthRegisterRequest::class)->validated();

        try {
            $this->authService->register($data);
        } catch (\Throwable $e) {
            logError($e);

            return redirect(route('auth.register'))
                ->with('failed', trans('messages.auth.register_failed'))
                ->withInput();
        }

        return redirect(route('auth.login'))
            ->with('success', trans('messages.HasSendMailVerify'));
    }

    // ===== Verify email ===============================================

    public function verifyEmail(Request $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        [$code, $email] = $this->authService->decodeToken($request->get('token'));
        $success = $this->authService->verifyEmail($code, $email);

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.login'), 'href' => route('auth.login'), 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.verify_email'), 'href' => route('auth.verifyEmail'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.verify_email.title', 'auth.verify_email.description');

        return $this->render('web::auth.verify', [
            'success' => $success,
        ]);
    }

    // ===== Forgot password ============================================

    public function forgotPassword(Request $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }
        if ($request->isMethod('post')) {
            return $this->handleForgotPassword();
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.login'), 'href' => route('auth.login'), 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.forgot_password'), 'href' => route('auth.forgotPassword'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.forgot_password.title', 'auth.forgot_password.description');

        return $this->render('web::auth.forgot_password');
    }

    protected function handleForgotPassword()
    {
        $data = app(AuthForgotPasswordRequest::class)->validated();

        $sent = $this->authService->sendResetLink((string) $data['email']);
        if (! $sent) {
            return redirect(route('auth.forgotPassword'))
                ->with('failed', trans('messages.MemberNotFound'))
                ->withInput();
        }

        return redirect(route('auth.forgotPassword'))
            ->with('success', trans('messages.HasSendMailForgetPassword'));
    }

    // ===== Reset password (qua link token) ============================

    public function changePassword(Request $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }
        if ($request->isMethod('post')) {
            return $this->handleChangePassword($request);
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.login'), 'href' => route('auth.login'), 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.change_password'), 'href' => route('auth.changePassword'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.change_password.title', 'auth.change_password.description');

        return $this->render('web::auth.change_password');
    }

    protected function handleChangePassword(Request $request)
    {
        [$code, $email] = $this->authService->decodeToken($request->get('token'));
        if (! $this->authService->isResetTokenValid($code, $email)) {
            return back()->with('failed', trans('messages.TokenInvalid'))->withInput();
        }

        $data = app(AuthResetPasswordRequest::class)->validated();

        $user = $this->authService->resetPassword($email, (string) $data['password']);
        if (! $user) {
            return back()->with('failed', trans('messages.ErrorAction'))->withInput();
        }

        return redirect(route('auth.login'))->with('success', trans('messages.ChangePasswordSuccess'));
    }
}
