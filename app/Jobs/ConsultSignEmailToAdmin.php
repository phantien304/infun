<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use App\Models\Entities\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
