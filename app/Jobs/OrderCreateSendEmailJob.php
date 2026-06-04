<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi email xác nhận đặt hàng cho khách. Dispatch từ
 * `CheckoutController::saveOrder` khi `customer.email` filled.
 *
 * Tham số:
 *  - $items     : list sản phẩm trong order (mảng đã enrich từ CartService)
 *  - $totalData : list dòng tính tổng (sub_total, coupon, voucher, shipping, total)
 *  - $customer  : array thông tin khách + order_status + payment_name + uniqid
 *
 * Job convention mới: extends nothing, implements ShouldQueue + standard
 * traits. KHÔNG còn extends `BaseInfunStudioJob` (class không tồn tại trong
 * project). KHÔNG dùng method `_handle()` cũ — Laravel scheduler gọi
 * `handle()` trực tiếp.
 */
class OrderCreateSendEmailJob implements ShouldQueue
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
        $mailer->orderCreate([$this->items, $this->totalData, $this->customer]);
    }
}
