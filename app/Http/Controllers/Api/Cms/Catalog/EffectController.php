<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\EffectData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\EffectRequest;
use App\Models\Entities\Effect;
use App\Repositories\Interfaces\EffectRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class EffectController extends BaseCmsController
{
    protected string $permission = 'effect';

    public function __construct(
        private readonly EffectRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return EffectData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAll()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])
            ->values();

        return respondSuccess($data);
    }

    public function store(EffectRequest $request)
    {
        $effect = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(EffectData::fromModel($effect), 'effect_created');
    }

    public function show(Effect $effect)
    {
        return respondSuccess(EffectData::fromModel($effect));
    }

    public function update(EffectRequest $request, Effect $effect)
    {
        $effect = $this->repo->saveFromCms($effect, $request->validated());

        return respondSuccess(EffectData::fromModel($effect), 'effect_updated');
    }

    public function destroy(Effect $effect)
    {
        $this->repo->deleteByIds([$effect->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $effect = $this->repo->restoreById((int) $id);
        abort_if($effect === null, 404);

        return respondSuccess(EffectData::fromModel($effect), 'effect_restored');
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
