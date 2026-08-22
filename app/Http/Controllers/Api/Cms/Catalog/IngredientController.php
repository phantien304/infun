<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\IngredientData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\IngredientRequest;
use App\Models\Entities\Ingredient;
use App\Repositories\Interfaces\IngredientRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class IngredientController extends BaseCmsController
{
    protected string $permission = 'ingredient';

    public function __construct(
        private readonly IngredientRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return IngredientData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(IngredientRequest $request)
    {
        $ingredient = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(IngredientData::fromModel($ingredient), 'ingredient_created');
    }

    public function show(Ingredient $ingredient)
    {
        $ingredient->load(['ingredientEffects', 'ingredientSafeties', 'ingredientSkincares']);

        return respondSuccess(IngredientData::fromModel($ingredient));
    }

    public function update(IngredientRequest $request, Ingredient $ingredient)
    {
        $ingredient = $this->repo->saveFromCms($ingredient, $request->validated());

        return respondSuccess(IngredientData::fromModel($ingredient), 'ingredient_updated');
    }

    public function destroy(Ingredient $ingredient)
    {
        $this->repo->deleteByIds([$ingredient->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $ingredient = $this->repo->restoreById((int) $id);
        abort_if($ingredient === null, 404);

        return respondSuccess(IngredientData::fromModel($ingredient), 'ingredient_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->repo->deleteByIds($data['ids'])
            : $this->repo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
