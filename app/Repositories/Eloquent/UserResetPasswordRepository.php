<?php

namespace App\Repositories\Client\InfunStudio;

use App\Model\Entities\UserResetPassword;
use App\Validators\Module\Client\InfunStudio\UserResetPasswordValidator;

class UserResetPasswordRepository extends BaseInfunStudioRepository
{
    public function model()
    {
        return UserResetPassword::class;
    }

    public function validator()
    {
        return UserResetPasswordValidator::class;
    }
}
