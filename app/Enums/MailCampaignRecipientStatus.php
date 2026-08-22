<?php

namespace App\Enums;

enum MailCampaignRecipientStatus: int
{
    case Pending = 0;
    case Sent    = 1;
    case Failed  = 2;
}
