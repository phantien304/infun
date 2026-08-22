<?php

namespace App\Services\Marketing;

use App\Enums\MailCampaignSendTo;
use App\Enums\MailCampaignStatus;
use App\Jobs\SendMarketingMailJob;
use App\Models\Entities\MailCampaign;
use App\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Repositories\Interfaces\MailCampaignRepositoryInterface;
use Illuminate\Http\UploadedFile;

/**
 * Dựng và khởi động một chiến dịch mail marketing.
 *
 * Khác bản mt219 (`Http/Controllers/Cms/MailController@save`) ở ba chỗ:
 *
 *  1. mt219 `dispatch(new SendMailJob(...))` MỘT job cho MỖI email. "Gửi tất
 *     cả khách" trên shop thật là vài chục nghìn job đẩy vào queue trong một
 *     request HTTP. Ở đây danh sách người nhận được ghi xuống DB trước, rồi
 *     chia lô RECIPIENTS_PER_JOB — số job tỉ lệ N/500, và request trả về
 *     ngay.
 *
 *  2. mt219 đọc cả file email bằng `getContent()` rồi `str_replace` +
 *     `explode` — file 50MB là 50MB trong RAM, nhân với vài bản sao chuỗi.
 *     Ở đây đọc theo dòng (fgets), gom lô 500 rồi ghi.
 *
 *  3. mt219 gửi xong không lưu vết gì. Ở đây mỗi người nhận là một dòng
 *     `mail_campaign_recipient` có trạng thái — vừa để tra cứu, vừa làm hàng
 *     đợi có state nên job chạy lại KHÔNG gửi trùng.
 */
class MailCampaignService
{
    /**
     * Số người nhận mỗi job. 500 là điểm cân bằng: đủ lớn để không đẻ hàng
     * chục nghìn job, đủ nhỏ để một job lỗi/timeout chỉ phải chạy lại phần
     * việc nhỏ (và các dòng đã `sent` trong lô đó cũng không bị gửi lại).
     */
    public const RECIPIENTS_PER_JOB = 500;

    /** Số dòng gom lại trước mỗi lần INSERT khi dựng danh sách người nhận. */
    private const INSERT_CHUNK = 500;

    public function __construct(
        private readonly MailCampaignRepositoryInterface $campaignRepo,
        private readonly CustomerRepositoryInterface $customerRepo,
    ) {
    }

    public function createAndDispatch(array $data, ?UploadedFile $file, ?int $createdBy): MailCampaign
    {
        $sendTo = MailCampaignSendTo::fromInput($data['send_to']);
        abort_if($sendTo === null, 422, trans('messages.cms.mail.send_to_invalid'));

        $campaign = $this->campaignRepo->create([
            'subject'       => $data['subject'],
            'message'       => $data['message'],
            'send_to'       => $sendTo->value,
            'user_group_id' => $sendTo === MailCampaignSendTo::UserGroup ? (int) $data['user_group_id'] : null,
            'status'        => MailCampaignStatus::Queued->value,
            'created_by'    => $createdBy,
        ]);

        $count = $sendTo === MailCampaignSendTo::File
            ? $this->fillFromFile($campaign->id, $file)
            : $this->fillFromCustomers($campaign->id, $sendTo, $data);

        $campaign->recipient_count = $count;
        $campaign->save();

        // Không có ai để gửi thì đóng chiến dịch luôn, đừng để nó treo ở
        // "queued" mãi làm admin tưởng đang chạy.
        if ($count === 0) {
            $campaign->status      = MailCampaignStatus::Completed->value;
            $campaign->finished_at = now();
            $campaign->save();

            return $campaign;
        }

        $this->dispatchBatches($campaign->id);

        return $campaign;
    }

    private function fillFromCustomers(int $campaignId, MailCampaignSendTo $sendTo, array $data): int
    {
        $inserted = 0;

        $this->customerRepo->chunkRecipients(
            newsletterOnly: $sendTo === MailCampaignSendTo::Newsletter,
            userGroupId: $sendTo === MailCampaignSendTo::UserGroup ? (int) $data['user_group_id'] : null,
            userIds: $sendTo === MailCampaignSendTo::Users
                ? array_map('intval', (array) ($data['user_ids'] ?? []))
                : null,
            callback: function (array $rows) use ($campaignId, &$inserted) {
                $inserted += $this->campaignRepo->insertRecipients($campaignId, $rows);
            },
            chunkSize: self::INSERT_CHUNK,
        );

        return $inserted;
    }

    /**
     * File danh sách email: mỗi dòng một địa chỉ. Dòng không phải email hợp
     * lệ bị BỎ QUA im lặng — file do người dùng dán tay luôn có dòng trống,
     * header, dấu phẩy thừa; dừng cả chiến dịch vì một dòng rác là phản tác
     * dụng. Số thực nhận nằm ở `recipient_count` để admin đối chiếu.
     */
    private function fillFromFile(int $campaignId, ?UploadedFile $file): int
    {
        abort_if($file === null, 422, trans('messages.cms.mail.file_required'));

        $handle = fopen($file->getRealPath(), 'rb');
        abort_if($handle === false, 422, trans('messages.cms.mail.file_unreadable'));

        $inserted = 0;
        $buffer   = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $email = trim($line, " \t\n\r\0\x0B,;\"'");
                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $buffer[] = ['email' => $email, 'user_id' => null];

                if (count($buffer) >= self::INSERT_CHUNK) {
                    $inserted += $this->campaignRepo->insertRecipients($campaignId, $buffer);
                    $buffer = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if (! empty($buffer)) {
            $inserted += $this->campaignRepo->insertRecipients($campaignId, $buffer);
        }

        return $inserted;
    }

    /**
     * Job chỉ được đẩy SAU khi transaction/ghi DB xong (afterCommit trên job)
     * — nếu không, worker nhanh tay có thể chạy trước lúc dòng recipient kịp
     * nhìn thấy được.
     */
    private function dispatchBatches(int $campaignId): void
    {
        $ids = $this->campaignRepo->pendingRecipientIds($campaignId);

        foreach ($ids->chunk(self::RECIPIENTS_PER_JOB) as $chunk) {
            dispatch(new SendMarketingMailJob($campaignId, $chunk->values()->all()));
        }
    }
}
