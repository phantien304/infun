<?php

namespace App\Http\Controllers\Client\InfunStudio;

class MaintenanceController extends BaseInfunStudioController
{
    public function index()
    {
        return $this->render('client.infunstudio.page.maintenance');
    }
}
