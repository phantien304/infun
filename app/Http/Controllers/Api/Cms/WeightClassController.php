<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\WeightClassData;
use App\Http\Requests\Cms\WeightClassRequest;
use App\Models\Entities\WeightClass;
use App\Repositories\Interfaces\WeightClassRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class WeightClassController extends BaseCmsController
{
    protected string $permission = 'weight-class';

    public function __construct(
        private readonly WeightClassRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return WeightClassData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($w) => [
                'id'    => $w->id,
                'value' => $w->value,
                'title' => $w->description?->title ?? '',
                'unit'  => $w->description?->unit ?? '',
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(WeightClassRequest $request)
    {
        $weightClass = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(WeightClassData::fromModel($weightClass), 'weight_class_created');
    }

    public function show(WeightClass $weightClass)
    {
        return respondSuccess(WeightClassData::fromModel($weightClass->load('descriptions')));
    }

    public function update(WeightClassRequest $request, WeightClass $weightClass)
    {
        $weightClass = $this->repo->saveFromCms($weightClass, $request->validated());

        return respondSuccess(WeightClassData::fromModel($weightClass), 'weight_class_updated');
    }

    public function destroy(WeightClass $weightClass)
    {
        $this->repo->deleteByIds([$weightClass->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $weightClass = $this->repo->restoreById((int) $id);
        abort_if($weightClass === null, 404);

        return respondSuccess(WeightClassData::fromModel($weightClass), 'weight_class_restored');
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
