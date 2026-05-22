<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class ErrorController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.404'), 'href' => route('error.404'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->setViewData([
            'titleSeo' => transClient('seo.404.title'),
            'descriptionSeo' => transClient('seo.404.description'),
            'linkCanonical' => route('error.404'),
        ]);
        return $this->render('client.infunstudio.page.error404');
    }
}
