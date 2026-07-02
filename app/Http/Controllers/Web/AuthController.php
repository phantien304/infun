<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AuthForgotPasswordRequest;
use App\Http\Requests\Web\AuthLoginRequest;
use App\Http\Requests\Web\AuthRegisterRequest;
use App\Http\Requests\Web\AuthResetPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Facades\Socialite;

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

    public function login()
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.login'), 'href' => route('auth.login'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.login.title', 'auth.login.description');

        return $this->render('web::auth.login');
    }

    public function doLogin(AuthLoginRequest $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        $data = $request->validated();

        $throttleKey = 'login:' . strtolower((string) $data['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return redirect(route('auth.login'))
                ->with('failed', trans('messages.auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]))
                ->withInput();
        }

        $ok = $this->authService->login(
            (string) $data['email'],
            (string) $data['password'],
        );

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);

            return redirect(route('auth.login'))
                ->with('failed', trans('messages.auth.login_failed'))
                ->withInput();
        }

        RateLimiter::clear($throttleKey);

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
                ->with('failed', trans('messages.auth.social_failed', ['provider' => ucfirst($provider)]));
        }
    }

    public function handleProviderCallback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            $this->authService->handleSocialUser($provider, $socialUser);

            return redirect(route('account.index'))
                ->with('success', trans('messages.auth.social_success', ['provider' => ucfirst($provider)]));
        } catch (\Throwable $e) {
            logError($e->getMessage());

            return redirect(route('auth.login'))
                ->with('failed', trans('messages.auth.social_failed', ['provider' => ucfirst($provider)]));
        }
    }

    // ===== Register ====================================================

    public function register()
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.register'), 'href' => route('auth.register'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.register.title', 'auth.register.description');

        return $this->render('web::auth.register');
    }

    public function doRegister(AuthRegisterRequest $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        try {
            $this->authService->register($request->validated());
        } catch (\Throwable $e) {
            logError($e);

            return redirect(route('auth.register'))
                ->with('failed', trans('messages.auth.register_failed'))
                ->withInput();
        }

        return redirect(route('auth.login'))
            ->with('success', trans('messages.auth.verify_email_sent'));
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

    public function forgotPassword()
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.login'), 'href' => route('auth.login'), 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.forgot_password'), 'href' => route('auth.forgotPassword'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.forgot_password.title', 'auth.forgot_password.description');

        return $this->render('web::auth.forgot_password');
    }

    public function doForgotPassword(AuthForgotPasswordRequest $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        $sent = $this->authService->sendResetLink((string) $request->validated()['email']);
        if (! $sent) {
            return redirect(route('auth.forgotPassword'))
                ->with('failed', trans('messages.auth.email_not_found'))
                ->withInput();
        }

        return redirect(route('auth.forgotPassword'))
            ->with('success', trans('messages.auth.reset_link_sent'));
    }

    // ===== Reset password (qua link token) ============================

    public function changePassword()
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.login'), 'href' => route('auth.login'), 'separator' => false]);
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.change_password'), 'href' => route('auth.changePassword'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'auth.change_password.title', 'auth.change_password.description');

        return $this->render('web::auth.change_password');
    }

    public function doChangePassword(Request $request)
    {
        if (auth()->check()) {
            return redirect()->to(route('account.index'));
        }

        [$code, $email] = $this->authService->decodeToken($request->get('token'));
        if (! $this->authService->isResetTokenValid($code, $email)) {
            return back()->with('failed', trans('messages.auth.token_invalid'))->withInput();
        }

        // Validate mật khẩu SAU khi token hợp lệ (link chết thì bỏ qua, không báo lỗi mật khẩu).
        $data = app(AuthResetPasswordRequest::class)->validated();

        $user = $this->authService->resetPassword($email, (string) $data['password']);
        if (! $user) {
            return back()->with('failed', trans('messages.ErrorAction'))->withInput();
        }

        return redirect(route('auth.login'))->with('success', trans('messages.auth.password_changed'));
    }
}
