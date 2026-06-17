<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi email "tài khoản đã được kích hoạt" sau khi user xác thực email thành
 * công (AuthService::verifyEmail).
 *
 * Job convention mới: implements ShouldQueue + 4 trait, inject JobMailer.
 */
class AuthenticatedEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $email,
    ) {
    }

    public function handle(JobMailer $mailer): void
    {
        $mailer->authenticatedEmail($this->email);
    }
}
