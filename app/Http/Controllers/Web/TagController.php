<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogTagDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;

class TagController extends Controller
{
    public function __construct(
        protected BlogTagRepositoryInterface $blogTagRepo,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_tag'), 'href' => route('tags.getList'), 'separator' => false],
        ];
    }

    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_tags', 'seo_description_tags');

        return $this->render('web::tag.list', [
            'entities' => BlogTagDTO::collect($this->blogTagRepo->listAllCached()),
        ]);
    }
}
