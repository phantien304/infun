{{--
    Review section — Shopee-style UX, render lần đầu server-side + AJAX cho
    mọi tương tác (filter/sort/page/save/report/vote). Không append query
    string vào URL product page.

    Biến nhận từ controller (ProductController::index):
      $entity            — Product (đã có ratingAvg, ratingDistribution, reviewCount)
      $reviews           — LengthAwarePaginator<ReviewDTO> page đầu
      $criteria          — Collection<ReviewCriteriaDTO> 5 tiêu chí
      $tags              — Collection<ReviewTagDTO> chip preset (top 8)
      $criteriaAverages  — array<code => float>
      $hasReviewed       — bool: user đã review từ order đã giao chưa
      $reviewPolicy      — string policy admin cấu hình
      $hasVerifiedPurchase — bool

    AJAX endpoint:
      GET routeArea('review.list', productId) — trả partial _comment_list.
      POST routeArea('review.saveReview')
      POST routeArea('review.vote')
      POST routeArea('review.report')

    Sort token theo repo: review.helpful_count / review.created_at / review.rating
    (prefix bảng để disambiguate JOIN trong QueryableRepository).
--}}

@php
    $reviewCount         = $entity->reviewCount ?? ($entity->review_count ?? 0);
    $ratingAvg           = (float) ($entity->ratingAvg ?? ($entity->rating_avg ?? 0));
    $distribution        = $entity->ratingDistribution ?? ($entity->rating_distribution ?? null);
    $distribution        = is_string($distribution) ? json_decode($distribution, true) : ($distribution ?? []);
    $criteria            = $criteria ?? collect();
    $tags                = $tags ?? collect();
    $criteriaAverages    = $criteriaAverages ?? [];
    $reviews             = $reviews ?? null;
    $hasReviewed         = $hasReviewed ?? false;
    $reviewPolicy        = $reviewPolicy ?? getCoreConfig('review.default_policy');
    $hasVerifiedPurchase = $hasVerifiedPurchase ?? false;

    // State filter ban đầu — tất cả đặt trong section data-attribute để JS đọc,
    // KHÔNG append vào URL của product detail.
    $initialFilter = ['rating' => null, 'has_media' => false, 'has_text' => false, 'tags' => []];
    $initialSort   = '-review.helpful_count';
@endphp

<section class="review-shopee"
         id="review-section"
         data-product-id="{{ $entity->id }}"
         data-list-url="{{ routeArea('review.list', $entity->id) }}"
         data-save-url="{{ routeArea('review.saveReview') }}"
         data-vote-url="{{ routeArea('review.vote') }}"
         data-report-url="{{ routeArea('review.report') }}"
         data-csrf="{{ csrf_token() }}">
    @include('web.product.structure._comment_summary')
    @include('web.product.structure._comment_filter')

    {{-- Container động — LAZY LOAD qua AJAX (tối ưu A 2026-06-10).
         Skeleton placeholder hiển thị trong khi JS fetch /review/list/{id}.
         JS reload() chạy ngay khi DOMContentLoaded ở _comment_script. --}}
    <div id="review-list-container">
        <div class="review-skeleton">
            @for ($i = 0; $i < 3; $i++)
                <div class="rs-item">
                    <div class="rs-avatar"></div>
                    <div class="rs-body">
                        <div class="rs-line rs-line--name"></div>
                        <div class="rs-line rs-line--stars"></div>
                        <div class="rs-line rs-line--text"></div>
                        <div class="rs-line rs-line--text rs-line--short"></div>
                    </div>
                </div>
            @endfor
        </div>
    </div>

    @include('web.product.structure._comment_form')
    @include('web.product.structure._comment_report')
</section>

@include('web.product.structure._comment_styles')
@include('web.product.structure._comment_script')
