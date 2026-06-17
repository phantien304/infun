<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi email xác thực tài khoản sau khi đăng ký (AuthService::register).
 *
 * Job convention mới (xem CLAUDE.md "Jobs"): implements ShouldQueue + 4 trait
 * Laravel, inject JobMailer qua handle(). KHÔNG còn extends
 * `BaseInfunStudioJob` / method `_handle()` legacy.
 *
 * Link xác thực = config_domain + url.verify_email + base64(code+'+'+email).
 */
class VerifyEmailJob implements ShouldQueue
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
        $path = ltrim((string) getModuleConfig('url.verify_email'), '/');
        $link = $domain . '/' . $path . base64_encode($this->code . '+' . $this->email);

        $mailer->verifyEmail($this->email, $link);
    }
}
