<?php

namespace App\Jobs\Client\InfunStudio;

class AuthenticatedEmailJob extends BaseInfunStudioJob
{
    protected function _handle()
    {
        $params = $this->getParams();
        $this->getMailer()->authenticatedEmail($params[0]);
    }
}
