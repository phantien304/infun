<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CurrencyData;
use App\Http\Requests\Cms\CurrencyRequest;
use App\Models\Entities\Currency;
use App\Repositories\Interfaces\CurrencyRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class CurrencyController extends BaseCmsController
{
    protected string $permission = 'currency';

    public function __construct(
        private readonly CurrencyRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return CurrencyData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'code' => $c->code,
                'title' => $c->title,
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(CurrencyRequest $request)
    {
        $currency = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(CurrencyData::fromModel($currency), 'currency_created');
    }

    public function show(Currency $currency)
    {
        return respondSuccess(CurrencyData::fromModel($currency));
    }

    public function update(CurrencyRequest $request, Currency $currency)
    {
        $currency = $this->repo->saveFromCms($currency, $request->validated());

        return respondSuccess(CurrencyData::fromModel($currency), 'currency_updated');
    }

    public function destroy(Currency $currency)
    {
        if ($this->repo->idsBlockedFromDelete([$currency->id])) {
            return respondUnprocessable(trans('messages.currency.cannot_delete_last'));
        }

        $this->repo->deleteByIds([$currency->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $currency = $this->repo->restoreById((int) $id);
        abort_if($currency === null, 404);

        return respondSuccess(CurrencyData::fromModel($currency), 'currency_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        if ($data['action'] === 'delete') {
            if ($this->repo->idsBlockedFromDelete($data['ids'])) {
                return respondUnprocessable(trans('messages.currency.cannot_delete_last'));
            }

            return respondSuccess(['affected' => $this->repo->deleteByIds($data['ids'])]);
        }

        return respondSuccess(['affected' => $this->repo->restoreByIds($data['ids'])]);
    }
}
