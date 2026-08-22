<?php

namespace App\Http\Controllers\Api\Cms\Review;

use App\Data\Cms\ReviewData;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\ReviewRequest;
use App\Models\Entities\Review;
use App\Models\Entities\ReviewReport;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\PaginatedDataCollection;

class ReviewController extends BaseCmsController
{
    protected string $permission = 'review';

    public function __construct(
        private readonly ReviewRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return ReviewData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function show($id)
    {
        $review = $this->repo->getForCms((int) $id);
        abort_if($review === null, 404);

        return respondSuccess(ReviewData::fromModel($review));
    }

    /**
     * Admin tạo review "mồi" (seed review) cho sản phẩm mới chưa có đánh
     * giá thật. Review do khách viết thì KHÔNG qua đây (không có nút Add
     * trên review khách gửi — chỉ trang này dùng cho review admin tự thêm).
     */
    public function store(ReviewRequest $request)
    {
        $review = $this->repo->createFromCms($request->validated());
        $review = $this->repo->getForCms($review->id);

        return respondCreated(ReviewData::fromModel($review), 'review_created');
    }

    /**
     * Admin toàn quyền sửa nội dung review (author/title/text/rating theo
     * từng tiêu chí/tag) + status (duyệt/từ chối/ẩn) — dùng cho việc kiểm
     * duyệt/chỉnh sửa review khách gửi (vd sửa lỗi chính tả, ẩn thông tin
     * nhạy cảm, sửa rating nhập sai...). product_id KHÔNG cho sửa (đổi sản
     * phẩm gắn review kéo theo phải dời rating đã cộng dồn sang sản phẩm
     * khác — ngoài phạm vi tính năng này). `rating` tổng tự tính lại từ
     * `ratings` nếu có (xem ReviewRepository::updateFromCms()) nên FE luôn
     * gửi `rating` nhưng server sẽ bỏ qua khi review có breakdown theo tiêu
     * chí. Đổi qua repo->updateFromCms() (Eloquent save() bình thường) để
     * ReviewObserver cộng/trừ đúng rating trung bình sản phẩm khi status
     * hoặc rating đổi — KHÔNG update() raw SQL.
     */
    public function update(Request $request, Review $review)
    {
        $data = $request->validate([
            'author'                        => 'required|string|max:64',
            'title'                         => 'nullable|string|max:255',
            'text'                          => 'required|string|max:5000',
            'rating'                        => 'required|integer|min:1|max:5',
            'status'                        => ['required', Rule::in(array_column(ReviewStatus::cases(), 'value'))],
            'is_publish'                    => 'nullable|boolean',
            'is_anonymous'                  => 'nullable|boolean',
            'ratings'                       => 'nullable|array',
            'ratings.*.review_criteria_id'  => 'required_with:ratings|integer|exists:review_criteria,id',
            'ratings.*.rating'              => 'required_with:ratings|integer|min:1|max:5',
            'tag_ids'                       => 'nullable|array',
            'tag_ids.*'                     => 'integer|exists:review_tag,id',
        ]);

        $review = $this->repo->updateFromCms($review, $data);

        return respondSuccess(ReviewData::fromModel($review), 'review_updated');
    }

    public function destroy(Review $review)
    {
        $this->repo->deleteByIds([$review->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $review = $this->repo->restoreById((int) $id);
        abort_if($review === null, 404);

        return respondSuccess(ReviewData::fromModel($review), 'review_restored');
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

    public function reply(Request $request, Review $review)
    {
        $data = $request->validate([
            'text' => 'required|string|max:5000',
        ]);

        $this->repo->addAdminReply($review, $data['text']);

        $review = $this->repo->getForCms($review->id);

        return respondSuccess(ReviewData::fromModel($review), 'review_reply_created');
    }

    public function resolveReport(Request $request, $id, $reportId)
    {
        $data = $request->validate([
            'status'          => ['required', Rule::in([
                ReviewReport::STATUS_RESOLVED,
                ReviewReport::STATUS_DISMISSED,
            ])],
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $report = $this->repo->resolveReport((int) $reportId, (int) $data['status'], $data['resolution_note'] ?? null);
        abort_if($report === null, 404);

        $review = $this->repo->getForCms((int) $id);

        return respondSuccess(ReviewData::fromModel($review), 'review_report_resolved');
    }
}
