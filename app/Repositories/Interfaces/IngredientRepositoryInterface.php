<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Ingredient;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface IngredientRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Ingredient;

    public function saveFromCms(?Ingredient $ingredient, array $data): Ingredient;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Ingredient;
}
