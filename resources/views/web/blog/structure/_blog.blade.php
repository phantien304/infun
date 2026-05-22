@php
    $categoryName = $categoryUrl = '';
    if (isset($item->category)) {
        $categoryName = $item->category->title;
        $categoryUrl = $item->category->url;
    }
    $blogUrl = $item->url;
    $blogTitle = $item->title;
    $fullName = getConfigDb('config_name');
    if (isset($item->user)) {
        $fullName = $item->user->full_name;
    }
@endphp
<article class="col-xl-4 col-lg-6 col-md-6 text-center wow fadeIn animated hover-up mb-30 animated">
    <div class="post-thumb">
        <a href="{!! $blogUrl !!}" title="{!! $blogTitle !!}">
            <img class="border-radius-15" src="{!! $item->thumbnail(370, 300) !!}" alt="{!! $blogTitle !!}">
        </a>
    </div>
    <div class="entry-content-2">
        @if (filled($categoryName))
            <h6 class="mb-10 font-sm">
                <a href="{!! $categoryUrl !!}" title="{!! $categoryName !!}" class="entry-meta text-muted">
                    {!! $categoryName !!}
                </a>
            </h6>
        @endif
        <h4 class="post-title mb-15">
            <a href="{!! $blogUrl !!}" title="{!! $blogTitle !!}" rel="bookmark">
                {!! $blogTitle !!}
            </a>
        </h4>
        <div class="entry-meta font-xs color-grey mt-10 pb-10">
            <div>
                <span class="post-on mr-10">{!! $item->publishedDate !!}</span>
                <span class="hit-count has-dot mr-10">{{ $item->viewed }} lượt xem</span>
            </div>
        </div>
    </div>
</article>
