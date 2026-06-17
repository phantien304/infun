<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi email chứa link đặt lại mật khẩu (AuthService::sendResetLink).
 *
 * Job convention mới: implements ShouldQueue + 4 trait, inject JobMailer.
 * Link = config_domain + url.forgot_password + base64(code+'+'+email).
 */
class ForgotPasswordJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $code,
        public string $email,
    ) {
    }

    public function handle(JobMailer $mailer): void
    {
        $domain = rtrim((string) (getConfigDb('config_domain') ?: config('app.url')), '/');
        $path = ltrim((string) getModuleConfig('url.forgot_password'), '/');
        $link = $domain . '/' . $path . base64_encode($this->code . '+' . $this->email);

        $mailer->forgotPassword($this->email, $link);
    }
}
