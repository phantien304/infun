<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class ErrorController extends Controller
{
    public function __construct()
    {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.404'), 'href' => route('error.404'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->setViewData([
            'titleSeo' => trans('seo.404.title'),
            'descriptionSeo' => trans('seo.404.description'),
            'linkCanonical' => route('error.404'),
        ]);
        return $this->render('web::page.error404');
    }
}
