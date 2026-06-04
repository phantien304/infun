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
 * Gửi email "tư vấn ký hợp đồng" cho admin khi user submit form `consultSign`
 * trên trang chi tiết sản phẩm.
 *
 * Tham số:
 *  - $product : model Product (auto serialize qua SerializesModels — eager
 *               relations sẽ bị bỏ, mailer phải tự re-fetch description nếu cần)
 *  - $params  : raw input form (full_name, telephone, email, comment...)
 *  - $options : option payload từ form (cùng shape như addToCart)
 */
class ConsultSignEmailToAdmin implements ShouldQueue
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
        $mailer->consultSignToAdmin([
            'product' => $this->product,
            'params'  => $this->params,
            'options' => $this->options,
        ]);
    }
}
