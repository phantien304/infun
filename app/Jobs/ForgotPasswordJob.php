<?php

namespace App\Jobs\Client\InfunStudio;

class ForgotPasswordJob extends BaseInfunStudioJob
{
    protected function _handle()
    {
        $params = $this->getParams();
        $link = getConfigDb('config_domain') . getInfunStudioConfig('url.forgot_password') . base64_encode($params[0] . '+' . $params[1]);

        $this->getMailer()->forgotPassword($params[1], $link);
    }
}
