<?php

namespace App\Http\Controllers\Api\Cms\Review;

use App\Data\Cms\ReviewTagData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\ReviewTagRequest;
use App\Models\Entities\ReviewTag;
use App\Repositories\Interfaces\ReviewTagRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class ReviewTagController extends BaseCmsController
{
    protected string $permission = 'review-tag';

    public function __construct(
        private readonly ReviewTagRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return ReviewTagData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(ReviewTagRequest $request)
    {
        $tag = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(ReviewTagData::fromModel($tag), 'review_tag_created');
    }

    public function show(ReviewTag $reviewTag)
    {
        $reviewTag->load(['descriptions']);

        return respondSuccess(ReviewTagData::fromModel($reviewTag));
    }

    public function update(ReviewTagRequest $request, ReviewTag $reviewTag)
    {
        $reviewTag = $this->repo->saveFromCms($reviewTag, $request->validated());

        return respondSuccess(ReviewTagData::fromModel($reviewTag), 'review_tag_updated');
    }

    public function destroy(ReviewTag $reviewTag)
    {
        $this->repo->deleteByIds([$reviewTag->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $tag = $this->repo->restoreById((int) $id);
        abort_if($tag === null, 404);

        return respondSuccess(ReviewTagData::fromModel($tag), 'review_tag_restored');
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
