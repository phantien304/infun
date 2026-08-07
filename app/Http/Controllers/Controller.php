<?php

namespace App\Http\Controllers;

use App\Events\BaseEvent;
use App\Http\Supports\BuildsSeoMeta;
use App\Http\Supports\MenusClient;
use App\Repositories\Interfaces\AttributeRepositoryInterface;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\DistrictRepositoryInterface;
use App\Repositories\Interfaces\FilterRepositoryInterface;
use App\Repositories\Interfaces\LanguageRepositoryInterface;
use App\Repositories\Interfaces\ManufacturerRepositoryInterface;
use App\Repositories\Interfaces\MenuRepositoryInterface;
use App\Repositories\Interfaces\MenuValueRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\LengthClassRepositoryInterface;
use App\Repositories\Interfaces\OptionRepositoryInterface;
use App\Repositories\Interfaces\StockStatusRepositoryInterface;
use App\Repositories\Interfaces\TaxClassRepositoryInterface;
use App\Repositories\Interfaces\UserGroupRepositoryInterface;
use App\Repositories\Interfaces\WardRepositoryInterface;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;
use App\Repositories\Interfaces\WeightClassRepositoryInterface;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use App\Services\Currency\CurrencyService;
use Illuminate\Container\Container;

abstract class Controller
{
    use BaseEvent;
    use MenusClient;
    use BuildsSeoMeta;
    protected $breadcrumbs = [];
    protected $viewData = [];
    private array $resolved = [];

    protected function lazyMap(): array
    {
        return [
            'categoryRepo'     => CategoryRepositoryInterface::class,
            'zoneRepo'         => ZoneRepositoryInterface::class,
            'manufacturerRepo' => ManufacturerRepositoryInterface::class,
            'filterRepo'       => FilterRepositoryInterface::class,
            'menuRepo'         => MenuRepositoryInterface::class,
            'menuValueRepo'    => MenuValueRepositoryInterface::class,
            'productRepo'      => ProductRepositoryInterface::class,
            'languageRepo'     => LanguageRepositoryInterface::class,
            'currencyService'  => CurrencyService::class,
            'districtRepo'     => DistrictRepositoryInterface::class,
            'wardRepo'         => WardRepositoryInterface::class,
            'attributeRepo'    => AttributeRepositoryInterface::class,
            'optionRepo'       => OptionRepositoryInterface::class,
            'stockStatusRepo'  => StockStatusRepositoryInterface::class,
            'taxClassRepo'     => TaxClassRepositoryInterface::class,
            'userGroupRepo'    => UserGroupRepositoryInterface::class,
            'lengthClassRepo'  => LengthClassRepositoryInterface::class,
            'weightClassRepo'  => WeightClassRepositoryInterface::class,
            'warehouseRepo'    => WarehouseRepositoryInterface::class,
        ];
    }

    public function __get(string $name): mixed
    {
        $map = $this->lazyMap();
        if (!isset($map[$name])) {
            throw new \RuntimeException(
                "Property [{$name}] không có trong " . static::class .
                    ". Các property hợp lệ: " . implode(', ', array_keys($map))
            );
        }
        return $this->resolved[$name] ??= Container::getInstance()->make($map[$name]);
    }

    public function __isset(string $name): bool
    {
        return isset($this->lazyMap()[$name]);
    }

    public function getControllerBySlug($slug)
    {
        if (empty($slug)) {
            return ['', ''];
        }
        $segments = explode('-', $slug);
        $lastSegment = preg_replace('/[^A-Za-z0-9]/', '', end($segments));
        $matches = preg_split("/(,?\s+)|((?<=[a-z])(?=\d))|((?<=\d)(?=[a-z]))/", $lastSegment);
        if (count($matches) < 2) {
            return ['App\Http\Controllers\Web\ErrorController', ''];
        }
        $typeUrl = $matches[0];
        $id = $matches[1];
        $map = [
            getModuleConfig('url.category')     => 'CategoryController',
            getModuleConfig('url.product')      => 'ProductController',
            getModuleConfig('url.manufacturer') => 'ManufacturerController',
            getModuleConfig('url.information') => 'InformationController',
            getModuleConfig('url.blog_category') => 'BlogCategoryController',
            getModuleConfig('url.blog')         => 'BlogController',
            getModuleConfig('url.store_review')  => 'StoreReviewController',
            getModuleConfig('url.tag')  => 'TagController',
        ];
        $controllerName = $map[$typeUrl] ?? 'ErrorController';
        $controllerClass = "App\Http\Controllers\Web\\" . $controllerName;
        return [$controllerClass, $id];
    }

    protected function toUrl(string $url, $params = [])
    {
        if (str_contains($url, 'http')) {
            return redirect()->to($url);
        }
        if (str_contains($url, '.')) {
            return redirect()->to(route($url, $params))->with($params);
        }
        return redirect()->to($url)->with($params);
    }

    public function forward($controller, $action, $params = [])
    {
        $instance = app()->make($controller);
        return app()->call([$instance, $action], $params);
    }

    public function render($view = null, array $data = [], array $mergeData = [])
    {
        $breadcrumbs = $this->getBreadcrumb();
        $breadcrumbSchema = [];
        foreach ($breadcrumbs as $index => $breadcrumb) {
            $breadcrumbSchema[] = [
                "@type" => "ListItem",
                "position" => $index + 1,
                "item" => $breadcrumb['href'],
                "name" => $breadcrumb['text']
            ];
        }
        $currentCurrency = $this->currencyService->currentCurrency();
        $currencySymbol = trim((string) ($currentCurrency->symbol_right ?? ''))
            ?: trim((string) ($currentCurrency->symbol_left ?? ''));

        $this->setViewData([
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => $breadcrumbSchema,
            'menus' => $this->getMenus(),
            'menuTree' => $this->getMenuTree(),
            'currencies' => $this->currencyService->allCurrency(),
            'currentCurrency' => $currentCurrency,
            'currencySymbol' => $currencySymbol,
            'languages' => $this->languageRepo->listAllCached(),
            'currentLocale' => strtolower(app()->getLocale()),
        ]);
        $this->buildDataCommon();
        $data = array_merge($data, $this->getViewData());

        \App\Helpers\ThemeManager::applyForView(is_string($view) ? $view : null);

        return view($view, $data, $mergeData);
    }

    protected function buildDataCommon()
    {
    }

    protected function getProductLatest(int $limit = 6)
    {
        return $this->productRepo->getProductLatest($limit);
    }

    public function getBreadcrumb()
    {
        return $this->breadcrumbs;
    }

    public function setBreadcrumb(array $breadcrumbs = [])
    {
        array_push($this->breadcrumbs, $breadcrumbs);
    }

    public function getViewData($key = null)
    {
        if ($key) {
            return data_get($this->viewData, $key);
        }
        return $this->viewData;
    }

    public function setViewData(array $viewData)
    {
        $this->viewData = array_merge($this->getViewData(), $viewData);
        return $this;
    }

    public function getParams()
    {
        return request()->all();
    }
}
