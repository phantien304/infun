<section class="section-padding pb-30 blog-latest">
    <div class="container-xl">
        <h3 class="text-center mb-30 text-9">Chia sẻ</h3>
        <div class="row">
            @foreach ($blogs as $item)
                @php
                    $blogUrl = $item->url;
                    $blogTitle = $item->title;
                @endphp
                <article class="col-xl-3 col-lg-4 col-md-6 text-center wow fadeIn animated hover-up mb-30 animated">
                    <div class="post-thumb">
                        <a href="{!! $blogUrl !!}" title="{!! $blogTitle !!}">
                            <img src="{!! $item->thumbnail(635, 420) !!}" alt="{!! $blogTitle !!}" class="border-radius-15">
                        </a>
                    </div>
                    <div class="entry-content-2">
                        <h4 class="post-title mb-15 font-xl">
                            <a href="{!! $blogUrl !!}" title="{!! $blogTitle !!}">
                                {!! $blogTitle !!}
                            </a>
                        </h4>
                        <div class="entry-meta font-xs color-grey mt-10 pb-10">
                            <div>
                                <span class="post-on mr-10">
                                    {!! $item->publishedDate !!}
                                </span>
                                <span class="hit-count has-dot mr-10">{!! $item->viewed !!} lượt xem</span>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
