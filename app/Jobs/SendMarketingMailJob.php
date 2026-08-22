<?php

namespace App\Jobs;

use App\Mail\Web\JobMailer;
use App\Models\Entities\MailCampaign;
use App\Repositories\Interfaces\MailCampaignRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Gửi một LÔ người nhận của chiến dịch mail marketing.
 *
 * Chống gửi trùng ở hai lớp:
 *  1. `MailCampaign::isSendable()` — chiến dịch đã Completed/Failed thì job
 *     đến muộn (retry sau khi worker chết) không làm gì.
 *  2. Từng dòng `mail_campaign_recipient` chỉ được lấy khi còn `pending`, và
 *     đánh dấu ngay sau khi gửi. Job chạy lại chỉ xử lý phần chưa gửi.
 *
 * Một địa chỉ hỏng KHÔNG được làm chết cả lô: bắt Throwable từng dòng, ghi
 * lỗi vào chính dòng đó rồi đi tiếp. Đây là khác biệt so với việc gộp cả lô
 * vào một transaction — mail đã bay đi rồi thì rollback cũng không gọi lại được.
 */
class SendMarketingMailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** Giãn cách retry: nhà cung cấp mail nghẽn thì dồn dập không giúp gì. */
    public array $backoff = [60, 300];

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public int $campaignId,
        public array $recipientIds,
    ) {
        $this->afterCommit = true;
    }

    public function handle(JobMailer $mailer, MailCampaignRepositoryInterface $repo): void
    {
        $campaign = MailCampaign::query()->find($this->campaignId);
        if (! $campaign || ! $campaign->isSendable()) {
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($repo->claimRecipients($this->recipientIds) as $recipient) {
            try {
                $mailer->marketing(
                    $recipient->email,
                    (string) $campaign->subject,
                    (string) $campaign->message,
                    $this->unsubscribeUrl($recipient->email),
                );
                $repo->markRecipientSent((int) $recipient->id);
                $sent++;
            } catch (Throwable $e) {
                $repo->markRecipientFailed((int) $recipient->id, $e->getMessage());
                $failed++;
            }
        }

        $repo->bumpCounters($this->campaignId, $sent, $failed);
        $repo->finalize($this->campaignId);
    }

    /**
     * Link huỷ nhận tin — signed URL nên không cần thêm cột token: chữ ký đã
     * chống sửa email trên query string, và link không hết hạn vì mail
     * marketing có thể nằm trong hộp thư hàng năm trời.
     */
    private function unsubscribeUrl(string $email): string
    {
        return URL::signedRoute('newsletter.unsubscribe', ['email' => $email]);
    }
}
