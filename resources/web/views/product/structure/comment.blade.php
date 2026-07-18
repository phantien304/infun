@php
    $reviewCount = $entity->reviewCount ?? ($entity->review_count ?? 0);
    $ratingAvg = (float) ($entity->ratingAvg ?? ($entity->rating_avg ?? 0));
    $distribution = $entity->ratingDistribution ?? ($entity->rating_distribution ?? null);
    $distribution = is_string($distribution) ? json_decode($distribution, true) : $distribution ?? [];
    $reviewCriteria = $reviewCriteria ?? collect();
    $reviewTags = $reviewTags ?? collect();
    $reviewCriteriaAverages = $reviewCriteriaAverages ?? [];
    $reviews = $reviews ?? null;
    $hasReviewed = $hasReviewed ?? false;
    $reviewPolicy = $reviewPolicy ?? \App\Enums\ReviewPolicy::default()->value;
    $hasVerifiedPurchase = $hasVerifiedPurchase ?? false;

    $initialFilter = ['rating' => null, 'has_media' => false, 'has_text' => false, 'tags' => []];
    $initialSort = '-review.helpful_count';
@endphp

<section class="review-shopee" id="review-section" data-product-id="{{ $entity->id }}"
    data-list-url="{{ route('review.list', $entity->id) }}" data-save-url="{{ route('review.saveReview') }}"
    data-vote-url="{{ route('review.vote') }}" data-report-url="{{ route('review.report') }}"
    data-csrf="{{ csrf_token() }}">
    @include('web::product.structure._comment_summary')
    @include('web::product.structure._comment_filter')
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

    @include('web::product.structure._comment_form')
    @include('web::product.structure._comment_report')
</section>

@include('web::product.structure._comment_styles')
@include('web::product.structure._comment_script')
