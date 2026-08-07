<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\StoreReviewData;
use App\Http\Requests\Cms\StoreReviewRequest;
use App\Models\Entities\StoreReview;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * StoreReview API (REST) cho CMS — mirror CategoryController.
 *
 * Convert từ mt219 app/Http/Controllers/Cms/StoreReviewController.php (Blade
 * + Presenter, action `save`/`updateSelected` riêng cho bulk update
 * name+featured từ list) sang REST chuẩn của infun: `updateSelected` không
 * còn cần thiết — bulk ở đây chỉ còn delete/restore (giống mọi module
 * khác qua `bulk()`), còn sửa nhanh 1 dòng thì gọi PUT như bình thường.
 */
class StoreReviewController extends BaseCmsController
{
    protected string $permission = 'store-review';

    public function __construct(
        private readonly StoreReviewRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return StoreReviewData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(StoreReviewRequest $request)
    {
        $storeReview = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(StoreReviewData::from($storeReview), 'store_review_created');
    }

    public function show(StoreReview $storeReview)
    {
        return respondSuccess(StoreReviewData::from($storeReview->load('descriptions')));
    }

    public function update(StoreReviewRequest $request, StoreReview $storeReview)
    {
        $storeReview = $this->repo->saveFromCms($storeReview, $request->validated());

        return respondSuccess(StoreReviewData::from($storeReview), 'store_review_updated');
    }

    public function destroy(StoreReview $storeReview)
    {
        $this->repo->deleteByIds([$storeReview->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $storeReview = $this->repo->restoreById((int) $id);
        abort_if($storeReview === null, 404);

        return respondSuccess(StoreReviewData::from($storeReview), 'store_review_restored');
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
