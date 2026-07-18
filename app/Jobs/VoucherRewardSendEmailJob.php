<?php

namespace App\Jobs;

use App\Enums\VoucherStatus;
use App\Mail\Web\JobMailer;
use App\Models\Entities\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VoucherRewardSendEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $voucherId
    ) {
        $this->afterCommit = true;
    }

    public function handle(JobMailer $mailer): void
    {
        $voucher = Voucher::query()->find($this->voucherId);
        if (! $voucher
            || (int) $voucher->status !== VoucherStatus::Active->value
            || trim((string) $voucher->to_email) === '') {
            return;
        }

        $claimed = Voucher::query()
            ->where('id', $voucher->id)
            ->whereNull('sent_at')
            ->update(['sent_at' => now()]);
        if ($claimed === 0) {
            return;
        }

        $mailer->voucherReward($voucher);
    }
}
