<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailCampaignRecipient extends Base
{
    protected $table = 'mail_campaign_recipient';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'campaign_id' => 'integer',
        'user_id'     => 'integer',
        'status'      => 'integer',
        'sent_at'     => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailCampaign::class, 'campaign_id', 'id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', \App\Enums\MailCampaignRecipientStatus::Pending->value);
    }
}
