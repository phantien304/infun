<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\ReviewCriteriaDTO;
use App\Data\Output\ReviewDTO;
use App\Data\Output\ReviewTagDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ReviewReportRequest;
use App\Http\Requests\Web\ReviewSaveRequest;
use App\Http\Requests\Web\ReviewVoteRequest;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService,
        protected ReviewRepositoryInterface $reviewRepo
    ) {
    }

    public function saveReview(ReviewSaveRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! $this->productRepo->findReviewableProduct((int) $validated['product_id'])) {
            return errNoValidator(trans('messages.ErrorAction'));
        }

        try {
            $review = $this->reviewService->submitReview($validated + [
                'ip'    => getIpVisitor(),
                'media' => $request->file('media', []),
            ]);

            return successData(
                trans('messages.ReviewSuccess'),
                ['review_id' => $review->id],
            );
        } catch (\DomainException $e) {
            return errNoValidator($e->getMessage());
        } catch (\Throwable $e) {
            logError('ReviewController::saveReview ' . $e->getMessage());
            return errNoValidator(trans('messages.ErrorAction'));
        }
    }

    public function vote(ReviewVoteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = (int) getCurrentUserId();

        try {
            $result = $this->reviewService->vote(
                (int) $validated['review_id'],
                $userId,
                (int) $validated['vote_type'],
            );
            return response()->json(['success' => true] + $result);
        } catch (\Throwable $e) {
            logError('ReviewController::vote ' . $e->getMessage());
            return errNoValidator(trans('messages.ErrorAction'));
        }
    }

    public function report(ReviewReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = (int) getCurrentUserId();

        try {
            $this->reviewService->report(
                (int) $validated['review_id'],
                $userId,
                $validated['reason_code'],
                $validated['description'] ?? null,
            );
            return successNoData(trans('messages.ReportSubmitted'));
        } catch (\Throwable $e) {
            logError('ReviewController::report ' . $e->getMessage());
            return errNoValidator(trans('messages.ErrorAction'));
        }
    }

    public function list(int $productId, Request $request)
    {
        $reviews = $this->reviewRepo->listForProduct($productId, $request);
        $userId  = (int) getCurrentUserId();

        $reviews->setCollection(
            $reviews->getCollection()->map(fn ($r) => ReviewDTO::fromModel($r, $userId ?: null)),
        );

        return view('web.product.structure._comment_list', [
            'reviews'  => $reviews,
            'criteria' => ReviewCriteriaDTO::collect($this->reviewRepo->getActiveCriteria()),
            'tags'     => ReviewTagDTO::collect($this->reviewRepo->getActiveTags()),
        ]);
    }
}
