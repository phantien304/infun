@php
    $storeReviewUrl = $item->getUrlClient();
    $storeReviewName = $item->name;
    $fullName = getConfigDb('config_name');
    if (isset($item->user)){
        $fullName = $item->user->full_name;
    }
@endphp
<article class="col-xl-4 col-lg-6 col-md-6 text-center wow fadeIn animated hover-up mb-30 animated">
    <div class="post-thumb">
        <a href="{!! $storeReviewUrl !!}" title="{!! $storeReviewName !!}">
            <img class="border-radius-15" src="{!! $item->getImageClient(370, 300) !!}" alt="{!! $storeReviewName !!}">
        </a>
    </div>
    <div class="entry-content-2">
        <h4 class="post-title mb-15">
            <a href="{!! $storeReviewUrl !!}" title="{!! $storeReviewName !!}" rel="bookmark">
                {!! $storeReviewName !!}
            </a>
        </h4>
        <div class="entry-meta font-xs color-grey mt-10 pb-10">
            <div>
                <span class="post-on mr-10">{!! $item->getCreatedAtValue('d/m/Y') !!}</span>
                <span class="hit-count has-dot mr-10">{{ $item->viewed }} lượt xem</span>
            </div>
        </div>
    </div>
</article>
