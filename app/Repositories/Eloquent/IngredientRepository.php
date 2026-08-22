<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Ingredient;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\IngredientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngredientRepository extends QueryableRepository implements IngredientRepositoryInterface
{
    public function model(): string
    {
        return Ingredient::class;
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = $request->input('sort') === 'name' ? 'name' : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Ingredient
    {
        return $this->resetModel()->withTrashed()
            ->with(['ingredientEffects', 'ingredientSafeties', 'ingredientSkincares'])
            ->find($id);
    }

    public function saveFromCms(?Ingredient $ingredient, array $data): Ingredient
    {
        return DB::transaction(function () use ($ingredient, $data) {
            $ingredient ??= new Ingredient();
            $ingredient->name         = $data['name'];
            $ingredient->description  = $data['description'] ?? null;
            $ingredient->warning      = (int) ($data['warning'] ?? 0);
            $ingredient->warning_text = $data['warning_text'] ?? null;
            $ingredient->save();

            $this->syncPivot('ingredient_effect', 'effect_id', $ingredient->id, $data['effect_ids'] ?? []);
            $this->syncPivot('ingredient_safety', 'safety_id', $ingredient->id, $data['safety_ids'] ?? []);
            $this->syncPivot('ingredient_skincare', 'skincare_id', $ingredient->id, $data['skincare_ids'] ?? []);

            return $ingredient->load(['ingredientEffects', 'ingredientSafeties', 'ingredientSkincares']);
        });
    }

    protected function syncPivot(string $table, string $column, int $ingredientId, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        DB::table($table)->where('ingredient_id', $ingredientId)->delete();

        if (! $ids) {
            return;
        }

        DB::table($table)->insert(array_map(
            fn (int $id) => ['ingredient_id' => $ingredientId, $column => $id],
            $ids
        ));
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Ingredient
    {
        $ingredient = $this->resetModel()->withTrashed()->find($id);
        $ingredient?->restore();

        return $ingredient?->load(['ingredientEffects', 'ingredientSafeties', 'ingredientSkincares']);
    }
}
