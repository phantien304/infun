<?php

namespace App\Http\Controllers\Client\InfunStudio;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class CsrfTokenController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            "message" => "GetSuccess",
            'data' => Session::token()
        ]);
    }
}
