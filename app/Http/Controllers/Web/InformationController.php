<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\InformationDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\InformationRepositoryInterface;

/**
 * Trang nội dung tĩnh (giới thiệu / chính sách...). Route đi qua catch-all
 * `/{slug?}` → HomeController::index → getControllerBySlug parse slug
 * (`getModuleConfig('url.information')` = 'i') → forward về index($id).
 *
 * Refactor sang pattern mới (xem CLAUDE.md "Cũ vs Mới"):
 *  - DI `InformationRepositoryInterface` (auto-bind ở AppServiceProvider).
 *  - DTO `InformationDTO` thay raw model + method legacy `getUrlClient()`.
 *  - `processMetaSeo` / `toUrl` thay `_processMetaSeo` / `_to`.
 *  - relation `description` (đã `->forLocale()`) thay `informationDescription`.
 */
class InformationController extends Controller
{
    public function __construct(
        protected InformationRepositoryInterface $informationRepo,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
        ];
    }

    public function index($id = '')
    {
        $entity = $this->informationRepo->getDetail($id);
        if (empty($entity) || empty($entity->description)) {
            return $this->toUrl('error.404');
        }

        // Tăng view counter — guard vì không phải mọi schema `information` đều
        // có cột `viewed`; lỗi đếm view KHÔNG được phá trang.
        try {
            $entity->increment('viewed');
        } catch (\Throwable $exception) {
            logError($exception->getMessage());
        }

        $informationDTO = InformationDTO::from($entity)->include('content');

        $this->setBreadcrumb(['text' => $informationDTO->title, 'href' => $informationDTO->url, 'separator' => false]);
        $this->processMetaSeo(
            'buildForSeoByData',
            $informationDTO->metaTitle ?: $informationDTO->title,
            $informationDTO->metaDescription ?: $informationDTO->description,
        );

        return $this->render('web::information.index', [
            'entity' => $informationDTO,
        ]);
    }
}
