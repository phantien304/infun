<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\TaxClassData;
use App\Http\Requests\Cms\TaxClassRequest;
use App\Models\Entities\TaxClass;
use App\Repositories\Interfaces\TaxClassRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class TaxClassController extends BaseCmsController
{
    protected string $permission = 'tax-class';

    public function __construct(
        private readonly TaxClassRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return TaxClassData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->getAll()
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->title])
            ->values();

        return respondSuccess($data);
    }

    public function store(TaxClassRequest $request)
    {
        $taxClass = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(TaxClassData::fromModel($taxClass), 'tax_class_created');
    }

    public function show(TaxClass $taxClass)
    {
        return respondSuccess(TaxClassData::fromModel($taxClass->load('taxRules')));
    }

    public function update(TaxClassRequest $request, TaxClass $taxClass)
    {
        $taxClass = $this->repo->saveFromCms($taxClass, $request->validated());

        return respondSuccess(TaxClassData::fromModel($taxClass), 'tax_class_updated');
    }

    public function destroy(TaxClass $taxClass)
    {
        $this->repo->deleteByIds([$taxClass->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $taxClass = $this->repo->restoreById((int) $id);
        abort_if($taxClass === null, 404);

        return respondSuccess(TaxClassData::fromModel($taxClass), 'tax_class_restored');
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
