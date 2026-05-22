<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\MenuValue;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\MenuValueRepositoryInterface;

class MenuValueRepository extends QueryableRepository implements MenuValueRepositoryInterface
{
    public function model(): string
    {
        return MenuValue::class;
    }
    public function getListMenuValueByMenuId($menuId)
    {
        $this->resetModel();
        return $this->model
            ->where('menu_id', $menuId)
            ->leftJoin('menu_value_description', 'menu_value_description.menu_value_id', '=', 'menu_value.id')
            ->languageCode('menu_value_description')
            ->select('id', 'item_id', 'parent_id', 'link', 'position', 'type', 'menu_id', 'css', 'html_custom', 'title')
            ->orderBy('position', 'ASC')
            ->get();
    }
}
