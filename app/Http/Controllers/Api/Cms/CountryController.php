<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CountryData;
use App\Http\Requests\Cms\CountryRequest;
use App\Models\Entities\Country;
use App\Repositories\Interfaces\CountryRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class CountryController extends BaseCmsController
{
    protected string $permission = 'country';

    public function __construct(
        private readonly CountryRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return CountryData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'name' => $c->name,
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(CountryRequest $request)
    {
        $country = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(CountryData::fromModel($country), 'country_created');
    }

    public function show(Country $country)
    {
        return respondSuccess(CountryData::fromModel($country));
    }

    public function update(CountryRequest $request, Country $country)
    {
        $country = $this->repo->saveFromCms($country, $request->validated());

        return respondSuccess(CountryData::fromModel($country), 'country_updated');
    }

    public function destroy(Country $country)
    {
        $this->repo->deleteByIds([$country->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $country = $this->repo->restoreById((int) $id);
        abort_if($country === null, 404);

        return respondSuccess(CountryData::fromModel($country), 'country_restored');
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
