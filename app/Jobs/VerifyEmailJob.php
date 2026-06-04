<?php

namespace App\Jobs\Client\InfunStudio;

class VerifyEmailJob extends BaseInfunStudioJob
{
    protected function _handle()
    {
        $params = $this->getParams();
        $link = getConfigDb('config_domain') . getInfunStudioConfig('url.verify_email') . base64_encode($params[0] . '+' . $params[1]);

        $this->getMailer()->verifyEmail($params[1], $link);
    }
}
