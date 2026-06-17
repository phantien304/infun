<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi email thông báo liên hệ mới cho admin. Dispatch khi
 * `config_email_notification` được set (gate ở ContactController::send).
 *
 * Refactor sang style Laravel mới (xem CLAUDE.md "Cũ vs Mới" — Jobs):
 *  - implements ShouldQueue + 4 trait, bỏ BaseInfunStudioJob / `_handle()`.
 *  - inject `JobMailer` qua `handle()` thay magic `getMailer()`.
 *  - `$params`: input form (name, email, phone, service, content) — mailer
 *    template `mailer.contact_create_to_admin` đọc cùng cấu trúc.
 */
class ContactSendEmailToAdminJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public array $params)
    {
    }

    public function handle(JobMailer $mailer): void
    {
        $mailer->contactCreateToAdmin($this->params);
    }
}
