<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\TaxRateData;
use App\Http\Requests\Cms\TaxRateRequest;
use App\Models\Entities\TaxRate;
use App\Repositories\Interfaces\TaxRateRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class TaxRateController extends BaseCmsController
{
    protected string $permission = 'tax-rate';

    public function __construct(
        private readonly TaxRateRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return TaxRateData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->getAll()
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
            ->values();

        return respondSuccess($data);
    }

    public function store(TaxRateRequest $request)
    {
        $taxRate = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(TaxRateData::fromModel($taxRate), 'tax_rate_created');
    }

    public function show(TaxRate $taxRate)
    {
        return respondSuccess(TaxRateData::fromModel($taxRate->load(['geoZone', 'taxRateToUserGroups'])));
    }

    public function update(TaxRateRequest $request, TaxRate $taxRate)
    {
        $taxRate = $this->repo->saveFromCms($taxRate, $request->validated());

        return respondSuccess(TaxRateData::fromModel($taxRate), 'tax_rate_updated');
    }

    public function destroy(TaxRate $taxRate)
    {
        $this->repo->deleteByIds([$taxRate->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $taxRate = $this->repo->restoreById((int) $id);
        abort_if($taxRate === null, 404);

        return respondSuccess(TaxRateData::fromModel($taxRate), 'tax_rate_restored');
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
