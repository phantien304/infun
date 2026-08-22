<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\AttributeData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\AttributeRequest;
use App\Models\Entities\Attribute;
use App\Repositories\Interfaces\AttributeRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class AttributeController extends BaseCmsController
{
    protected string $permission = 'attribute';

    public function __construct(
        private readonly AttributeRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return AttributeData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(AttributeRequest $request)
    {
        $attribute = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(AttributeData::fromModel($attribute), 'attribute_created');
    }

    public function show(Attribute $attribute)
    {
        $attribute->load(['descriptions', 'attributeValues.descriptions']);

        return respondSuccess(AttributeData::fromModel($attribute));
    }

    public function update(AttributeRequest $request, Attribute $attribute)
    {
        $attribute = $this->repo->saveFromCms($attribute, $request->validated());

        return respondSuccess(AttributeData::fromModel($attribute), 'attribute_updated');
    }

    public function destroy(Attribute $attribute)
    {
        $this->repo->deleteByIds([$attribute->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $attribute = $this->repo->restoreById((int) $id);
        abort_if($attribute === null, 404);

        return respondSuccess(AttributeData::fromModel($attribute), 'attribute_restored');
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
