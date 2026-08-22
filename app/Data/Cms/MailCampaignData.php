<?php

namespace App\Data\Cms;

use App\Models\Entities\MailCampaign;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO chiến dịch mail marketing cho CMS.
 *
 * mt219 không có bảng nào tương ứng — gửi xong là mất dấu. Ba con số
 * recipient/sent/failed là thứ khiến màn này khác một cái nút "gửi": mở lại
 * sau một ngày vẫn biết chiến dịch chạy tới đâu, hỏng bao nhiêu.
 */
class MailCampaignData extends Data
{
    public function __construct(
        public int $id,
        public string $subject,
        public ?string $message,
        public string $send_to,
        public ?int $user_group_id,
        public int $recipient_count,
        public int $sent_count,
        public int $failed_count,
        public int $status,
        public ?int $created_by,
        public ?string $author_name,
        public ?string $started_at,
        public ?string $finished_at,
        public ?string $created_at,
        public ?string $deleted_at,
        public Collection $recipients,
    ) {
    }

    /**
     * `message` là NULL ở màn danh sách — `listForCms()` cố tình không select
     * cột longtext đó: kéo 50 bài HTML về chỉ để hiện tiêu đề là lãng phí
     * băng thông, và FE danh sách không dùng tới.
     */
    public static function fromModel(MailCampaign $campaign): self
    {
        $author = $campaign->relationLoaded('author') ? $campaign->author : null;

        return new self(
            id: (int) $campaign->id,
            subject: (string) $campaign->subject,
            message: $campaign->message === null ? null : (string) $campaign->message,
            send_to: (string) $campaign->send_to,
            user_group_id: $campaign->user_group_id === null ? null : (int) $campaign->user_group_id,
            recipient_count: (int) $campaign->recipient_count,
            sent_count: (int) $campaign->sent_count,
            failed_count: (int) $campaign->failed_count,
            status: (int) $campaign->status,
            created_by: $campaign->created_by === null ? null : (int) $campaign->created_by,
            author_name: $author?->full_name ?? $author?->username ?? null,
            started_at: $campaign->started_at?->toDateTimeString(),
            finished_at: $campaign->finished_at?->toDateTimeString(),
            created_at: $campaign->created_at?->toDateTimeString(),
            deleted_at: $campaign->deleted_at?->toDateTimeString(),
            recipients: $campaign->relationLoaded('recipients')
                ? MailCampaignRecipientItemData::collect($campaign->recipients, Collection::class)
                : collect(),
        );
    }
}
