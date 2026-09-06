<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\LanguageData;
use App\Http\Requests\Cms\LanguageRequest;
use App\Models\Entities\Language;
use App\Repositories\Interfaces\LanguageRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class LanguageController extends BaseCmsController
{
    protected string $permission = 'language';

    public function __construct(
        private readonly LanguageRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return LanguageData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($l) => [
                'id'   => $l->id,
                'code' => $l->code,
                'name' => $l->name,
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(LanguageRequest $request)
    {
        $language = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(LanguageData::fromModel($language), 'language_created');
    }

    public function show(Language $language)
    {
        return respondSuccess(LanguageData::fromModel($language));
    }

    public function update(LanguageRequest $request, Language $language)
    {
        $language = $this->repo->saveFromCms($language, $request->validated());

        return respondSuccess(LanguageData::fromModel($language), 'language_updated');
    }

    public function destroy(Language $language)
    {
        if ($this->repo->idsBlockedFromDelete([$language->id])) {
            return respondUnprocessable(trans('messages.language.cannot_delete_last'));
        }

        $this->repo->deleteByIds([$language->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $language = $this->repo->restoreById((int) $id);
        abort_if($language === null, 404);

        return respondSuccess(LanguageData::fromModel($language), 'language_restored');
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
                return respondUnprocessable(trans('messages.language.cannot_delete_last'));
            }

            return respondSuccess(['affected' => $this->repo->deleteByIds($data['ids'])]);
        }

        return respondSuccess(['affected' => $this->repo->restoreByIds($data['ids'])]);
    }
}
