<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi email thông báo order mới cho admin. Dispatch khi
 * `config_email_notification` được set.
 *
 * Tham số: cùng shape với `OrderCreateSendEmailJob` — mailer template ở admin
 * (`mailer.order_create_to_admin`) đọc cùng cấu trúc.
 */
class OrderCreateSendEmailToAdminJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $items,
        public array $totalData,
        public array $customer,
    ) {
    }

    public function handle(JobMailer $mailer): void
    {
        $mailer->orderCreateToAdmin([$this->items, $this->totalData, $this->customer]);
    }
}
