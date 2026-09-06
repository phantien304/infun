<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\LengthClassData;
use App\Http\Requests\Cms\LengthClassRequest;
use App\Models\Entities\LengthClass;
use App\Repositories\Interfaces\LengthClassRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class LengthClassController extends BaseCmsController
{
    protected string $permission = 'length-class';

    public function __construct(
        private readonly LengthClassRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return LengthClassData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($l) => [
                'id'    => $l->id,
                'value' => $l->value,
                'title' => $l->description?->title ?? '',
                'unit'  => $l->description?->unit ?? '',
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(LengthClassRequest $request)
    {
        $lengthClass = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(LengthClassData::fromModel($lengthClass), 'length_class_created');
    }

    public function show(LengthClass $lengthClass)
    {
        return respondSuccess(LengthClassData::fromModel($lengthClass->load('descriptions')));
    }

    public function update(LengthClassRequest $request, LengthClass $lengthClass)
    {
        $lengthClass = $this->repo->saveFromCms($lengthClass, $request->validated());

        return respondSuccess(LengthClassData::fromModel($lengthClass), 'length_class_updated');
    }

    public function destroy(LengthClass $lengthClass)
    {
        $this->repo->deleteByIds([$lengthClass->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $lengthClass = $this->repo->restoreById((int) $id);
        abort_if($lengthClass === null, 404);

        return respondSuccess(LengthClassData::fromModel($lengthClass), 'length_class_restored');
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
