<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Client\InfunStudio\BannerRepository;
use App\Repositories\Client\InfunStudio\InformationRepository;

class InformationController extends Controller
{
    public function __construct(
        InformationRepository $informationRepository,
        BannerRepository $bannerRepository
    ) {
        parent::__construct();
        $this->setRepository($informationRepository);
        $this->registerRepository($bannerRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false]
        ];
    }

    public function index($id = '')
    {
        $entity = $this->getRepository()->getDetail($id);
        if (empty($entity) || !isset($entity->informationDescription)) {
            return $this->_to('error.404');
        }
        $this->_updateViewed($entity);

        $this->setBreadcrumb(['text' => $entity->informationDescription->title, 'href' => $entity->informationDescription->getUrlClient(), 'separator' => false]);

        $this->_processMetaSeo(
            '_buildForSeoByData',
            $entity->informationDescription->getMetaTitle(),
            $entity->informationDescription->getMetaDescription()
        );

        return $this->render('client.infunstudio.information.index', [
            'entity' => $entity
        ]);
    }
}
