<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class CsrfTokenController extends Controller
{
    public function index()
    {
        return respondSuccess(Session::token());
    }
}
