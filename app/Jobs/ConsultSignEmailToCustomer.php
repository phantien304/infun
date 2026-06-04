<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use App\Models\Entities\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi email xác nhận "tư vấn ký hợp đồng" cho khách. Cặp với
 * `ConsultSignEmailToAdmin`.
 *
 * Hiện tại `JobMailer::consultSignToCustomer()` chưa implement body — job
 * vẫn dispatch để giữ chỗ; khi mailer hoàn thiện sẽ tự chạy.
 */
class ConsultSignEmailToCustomer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Product $product,
        public array $params,
        public array $options,
    ) {
    }

    public function handle(JobMailer $mailer): void
    {
        $mailer->consultSignToCustomer([
            'product' => $this->product,
            'params'  => $this->params,
            'options' => $this->options,
        ]);
    }
}
