<?php

namespace App\Jobs\Client\InfunStudio;

class ContactSendEmailToAdminJob extends BaseInfunStudioJob
{
    protected function _handle()
    {
        $this->getMailer()->contactCreateToAdmin($this->getParams()[0]);
    }
}
