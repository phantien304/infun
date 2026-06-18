<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
