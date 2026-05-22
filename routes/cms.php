<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['check_js_request', 'cms']], function () {});
