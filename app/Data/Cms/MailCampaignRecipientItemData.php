<?php

namespace App\Data\Cms;

use App\Models\Entities\MailCampaignRecipient;
use Spatie\LaravelData\Data;

/**
 * Một người nhận trong chiến dịch. `error` là lý do gửi hỏng (SMTP từ chối,
 * địa chỉ sai…) — đây chính là thứ trả lời "vì sao khách này không nhận được".
 */
class MailCampaignRecipientItemData extends Data
{
    public function __construct(
        public int $id,
        public string $email,
        public ?int $user_id,
        public int $status,
        public ?string $error,
        public ?string $sent_at,
    ) {
    }

    public static function fromModel(MailCampaignRecipient $recipient): self
    {
        return new self(
            id: (int) $recipient->id,
            email: (string) $recipient->email,
            user_id: $recipient->user_id === null ? null : (int) $recipient->user_id,
            status: (int) $recipient->status,
            error: $recipient->error,
            sent_at: $recipient->sent_at?->toDateTimeString(),
        );
    }
}
