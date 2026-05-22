<?php

namespace App\Http\Supports;

trait MenusCms
{
    protected $_children = [];

    protected function _getTreeMenu($menuId)
    {
        $menuValueRepository = app()->make(\App\Repositories\Cms\MenuValueRepository::class);
        try {
            $childs = $menuValueRepository->getListMenuValueByMenuId($menuId);
            foreach ($childs as $child) {
                $this->_children[$child->parent_id . '_' . $menuId][] = $child;
            }
            $parent = 1;
            return $this->_genTree($parent, 1, $menuId);
        } catch (\Exception $e) {
            logError($e->getMessage());
            return;
        }
    }

    protected function _genTree($parent, $level, $menuId)
    {
        if ($this->_hasChild($parent, $menuId)) {
            $output = [];
            $data = $this->_getNodes($parent, $menuId);
            for ($i = 0; $i < count($data); $i++) {
                $output[$i]['id'] = $data[$i]->id;
                $output[$i]['text'] = $data[$i]->title;
                $output[$i]['position'] = $data[$i]->position;
                if ($data[$i]->id > 1) {
                    $children = $this->_genTree($data[$i]->id, $level + 1, $menuId);
                    if (filled($children)) {
                        $output[$i]['children'] = $children;
                    }
                }
            }
            return $output;
        }
        return null;
    }


    protected function _hasChild($id, $menuId = 0)
    {
        return isset($this->_children[$id . '_' . $menuId]);
    }

    protected function _getNodes($id, $menuId = 0)
    {
        return $this->_children[$id . '_' . $menuId];
    }
}
