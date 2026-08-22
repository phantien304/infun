<?php

namespace App\Models\Entities;

use App\Enums\MailCampaignStatus;
use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailCampaign extends Base
{
    use SoftDeletes;

    protected $table = 'mail_campaign';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'user_group_id'   => 'integer',
        'recipient_count' => 'integer',
        'sent_count'      => 'integer',
        'failed_count'    => 'integer',
        'status'          => 'integer',
        'created_by'      => 'integer',
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(MailCampaignRecipient::class, 'campaign_id', 'id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Chiến dịch còn được phép gửi tiếp không.
     *
     * Đã Completed/Failed thì job đến muộn (retry của worker chết) KHÔNG được
     * gửi lại — đây là chốt chặn cuối cùng ngoài cờ `status` trên từng
     * recipient.
     */
    public function isSendable(): bool
    {
        return in_array(
            (int) $this->status,
            [MailCampaignStatus::Queued->value, MailCampaignStatus::Sending->value],
            true,
        );
    }
}
