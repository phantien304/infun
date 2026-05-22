<?php

namespace App\Http\Controllers\Client\InfunStudio;

use Illuminate\Support\Facades\Session;

class CsrfTokenController extends BaseInfunStudioController
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
