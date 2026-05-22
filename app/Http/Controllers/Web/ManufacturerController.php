<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Client\InfunStudio\ManufacturerRepository;
use App\Repositories\Client\InfunStudio\ProductRepository;

class ManufacturerController extends Controller
{
    public function __construct(
        ManufacturerRepository $manufacturerRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct();
        $this->setRepository($manufacturerRepository);
        $this->registerRepository($productRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
        ];
    }

    public function index($id)
    {
        $entity = $this->getRepository()->getDetail($id);
        if (empty($entity)) {
            return $this->_to('error.404');
        }

        $this->setBreadcrumb(['text' => $entity->name, 'href' => $entity->getUrlClient(), 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByData', $entity->getMetaTitle('name'), $entity->getMetaDescription('name'));

        $this->_processRequest($id);

        $entities = $this->fetchRepository(ProductRepository::class)->getListForFrontend($this->getParams());

        return $this->render('client.infunstudio.manufacturer.list', [
            'entity' => $entity,
            'entities' => $entities,
        ]);
    }

    protected function _buildDataCommon()
    {
        $this->setViewData([
            'products' => $this->getProductLatest(),
        ]);
    }

    protected function _processRequest($id)
    {
        request()->merge([
            'manufacturer_id_eq' => $id
        ]);
    }
}
