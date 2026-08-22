<?php

namespace App\Http\Controllers\Api\Cms\Review;

use App\Data\Cms\ReviewCriteriaData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\ReviewCriteriaRequest;
use App\Models\Entities\ReviewCriteria;
use App\Repositories\Interfaces\ReviewCriteriaRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class ReviewCriteriaController extends BaseCmsController
{
    protected string $permission = 'review-criteria';

    public function __construct(
        private readonly ReviewCriteriaRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return ReviewCriteriaData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(ReviewCriteriaRequest $request)
    {
        $criteria = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(ReviewCriteriaData::fromModel($criteria), 'review_criteria_created');
    }

    // Laravel số ít hoá "review-criteria" -> "review_criterion" cho route
    // model binding (Str::singular('criteria') = 'criterion') — tên tham
    // số PHẢI khớp $reviewCriterion, khác asymmetric so với các entity khác.
    public function show(ReviewCriteria $reviewCriterion)
    {
        $reviewCriterion->load(['descriptions']);

        return respondSuccess(ReviewCriteriaData::fromModel($reviewCriterion));
    }

    public function update(ReviewCriteriaRequest $request, ReviewCriteria $reviewCriterion)
    {
        $reviewCriterion = $this->repo->saveFromCms($reviewCriterion, $request->validated());

        return respondSuccess(ReviewCriteriaData::fromModel($reviewCriterion), 'review_criteria_updated');
    }

    public function destroy(ReviewCriteria $reviewCriterion)
    {
        $this->repo->deleteByIds([$reviewCriterion->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $criteria = $this->repo->restoreById((int) $id);
        abort_if($criteria === null, 404);

        return respondSuccess(ReviewCriteriaData::fromModel($criteria), 'review_criteria_restored');
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
