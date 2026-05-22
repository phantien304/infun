<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Menu;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\MenuRepositoryInterface;

class MenuRepository extends QueryableRepository implements MenuRepositoryInterface
{
    public function model(): string
    {
        return Menu::class;
    }
    public function getMenuByPosition($position = 'top')
    {
        return $this->model
            ->where('position', $position)
            ->with([
                'values.description'
            ])
            ->get();
    }
}
