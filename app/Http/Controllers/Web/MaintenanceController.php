<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class MaintenanceController extends Controller
{
    public function index()
    {
        return view('web::page.maintenance');
    }
}
