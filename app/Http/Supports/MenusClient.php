<?php

namespace App\Http\Supports;

use App\Helpers\CacheGate;
use Illuminate\Support\Str;

trait MenusClient
{
    protected $children = [];

    protected function hasChild($id, $menuId = 0)
    {
        return isset($this->children[$id . '_' . $menuId]);
    }

    protected function getNodes($id, $menuId = 0)
    {
        return $this->children[$id . '_' . $menuId];
    }

    public function getMenus()
    {
        $cacheKey = getCoreConfig('cache.menu') . app()->getLocale();
        $store = CacheGate::systemStore();

        if (! $store->has($cacheKey)) {
            $menus = $this->buildMenus();
            $store->add($cacheKey, $menus);
            return $menus;
        }
        return $store->get($cacheKey);
    }

    protected function buildMenus(): array
    {
        $menus = [];
        $menuData = $this->menuRepo->getMenuByPosition('top');
        foreach ($menuData as $i => $item) {
            list($pc, $mobile) = $this->genTree($item);
            $menus[$i] = ['pc' => $pc, 'mobile' => $mobile];
        }
        return $menus;
    }

    public function genTree(mixed $menu)
    {
        $this->children = [];
        try {
            $childs = $this->menuValueRepo->getListMenuValueByMenuId($menu['id']);
            foreach ($childs as $child) {
                $this->children[$child->parent_id . '_' . $menu['id']][] = $child;
            }
            $output = $outputMobile = ' <nav>';
            list($pc, $mobile) = $this->getMenu(1, $menu['id']);
            // PC
            $output .= $pc;
            $output .= '</nav>';
            // Mobile
            $outputMobile .= $mobile;
            $outputMobile .= '</nav>';
            return [$output, $outputMobile];
        } catch (\Exception $e) {
            logError($e->getMessage());
        }
        return ['', ''];
    }

    public function getMenu($parent, $menuId)
    {
        $output = $outputMobile = '';
        if ($this->hasChild($parent, $menuId)) {
            $data = $this->getNodes($parent, $menuId);
            $output .= '<ul>';
            $outputMobile .= '<ul class="mobile-menu font-heading">';
            foreach ($data as $item) {
                try {
                    $link = $this->getLink($item);
                    if ($this->hasChild($item['id'], $menuId)) {
                        // PC
                        $output .= '<li class="' . $item['css'] . '">' . $item['html_custom'];
                        $output .= '<a data-link="' . $link . '" href="/' . $link . '" title="' . $item['title'] . '">' . $item['title'];
                        $output .= '<i class="fi-rs-angle-down"></i>';
                        $output .= '</a>';
                        // Mobile
                        $outputMobile .= '<li class="menu-item-has-children ' . $item['css'] . '">' . $item['html_custom'];
                        $outputMobile .= '<a data-link="' . $link . '" href="/' . $link . '" title="' . $item['title'] . '">' . $item['title'];
                        $outputMobile .= '</a>';

                        if ($item['id'] > 1) {
                            list($pc, $mobile) = $this->processMenu($item['id'], 1, $menuId);
                            $output .= $pc;
                            $outputMobile .= $mobile;
                        }
                        // PC
                        $output .= '</li>';
                        // Mobile
                        $outputMobile .= '</li>';
                        continue;
                    }
                    // PC
                    $output .= '<li class="' . $item['css'] . '">' . $item['html_custom'];
                    $output .= '<a data-link="' . $link . '" href="/' . $link . '" title="' . $item['title'] . '">' . $item['title'];
                    $output .= '</a></li>';
                    // Mobile
                    $outputMobile .= '<li class="' . $item['css'] . '">' . $item['html_custom'];
                    $outputMobile .= '<a data-link="' . $link . '" href="/' . $link . '" title="' . $item['title'] . '">' . $item['title'];
                    $outputMobile .= '</a></li>';
                } catch (\Exception $e) {
                    logError($e->getMessage());
                }
            }
            $output .= '</ul>';
            $outputMobile .= '</ul>';
        }
        return [$output, $outputMobile];
    }

    protected function processMenu($parent, $level, $menuId)
    {
        if ($this->hasChild($parent, $menuId)) {
            $data = $this->getNodes($parent, $menuId);
            $output = $outputMobile = '';
            $classSubmenu = ($level > 1) ? 'level-menu' : 'sub-menu';
            // PC
            $output .= '<ul class="' . $classSubmenu . '">';
            /// Mobile
            $outputMobile = '<ul class="dropdown">';
            foreach ($data as $item) {
                // PC
                list($pc, $mobile) = $this->_renderMenuContent($item, $level + 1, $menuId);
                $output .= $pc;
                // Mobile
                $outputMobile .= $mobile;
            }
            // PC
            $output .= '</ul>';
            // Mobile
            $outputMobile .= '</ul>';
            return [$output, $outputMobile];
        }
        return ['', ''];
    }

    protected function _renderMenuContent($menu, $level, $menuId = 0)
    {
        if ($this->hasChild($menu['id'], $menuId)) {
            // PC
            $output = '<li class="' . $menu['css'] . '">' . $menu['html_custom'];
            $output .= '<a data-link="' . $this->getLink($menu) . '" href="/' . $this->getLink($menu) . '" title="' . $menu['title'] . '">' . $menu['title'];
            $output .= '<i class="fi-rs-angle-right"></i></a>';
            // Mobile
            $outputMobile = '<li class="menu-item-has-children ' . $menu['css'] . '">' . $menu['html_custom'];
            $outputMobile .= '<a data-link="' . $this->getLink($menu) . '" href="/' . $this->getLink($menu) . '" title="' . $menu['title'] . '">' . $menu['title'];
            if ($menu['id'] > 1) {
                list($pc, $mobile) = $this->processMenu($menu['id'], $level, $menuId);
                $output .= $pc;
                $outputMobile .= $mobile;
            }
            $output .= '</li>';
            $outputMobile .= '</li>';
            return [$output, $outputMobile];
        }
        // PC & Mobile
        $output = '<li class="' . $menu['css'] . '">' . $menu['html_custom'];
        $output .= '<a data-link="' . $this->getLink($menu) . '" href="/' . $this->getLink($menu) . '" title="' . $menu['title'] . '">' . $menu['title'];
        $output .= '</a>';
        $output .= '</li>';
        return [$output, $output];
    }

    public function getLink($menu)
    {
        $id = (int)$menu['item_id'];
        $slug = Str::slug($menu['title']);
        switch ($menu['type']) {
            case 'category':
                return "{$slug}-" . getModuleConfig('url.category') . $id;
            case 'product':
                return "{$slug}-" . getModuleConfig('url.product') . $id;
            case 'information':
                return "{$slug}-" . getModuleConfig('url.information') . $id;
            case 'blogCategory':
                return "{$slug}-" . getModuleConfig('url.blog_category') . $id;
            default:
                return $menu['link'];
        }
    }
}
