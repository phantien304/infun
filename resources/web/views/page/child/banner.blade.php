{{--
    Banner / hero slider trang chủ.
    Slick slider gắn vào `.hero-slider-1` (init ở public/web/js/style.js).
    Class theme giữ nguyên: home-slider, hero-slider-1, single-hero-slider,
    slider-content, slider-image, slider-arrow, hero-slider-1-arrow.
    Chỉ utility wrapper (container-fluid, container-xl, d-flex) đổi sang Tailwind.
--}}
@section('banner')
    @foreach ($banners as $banner)
        <section class="home-slider relative mb-30 infunstudio">
            <div class="w-full">
                <div class="home-slide-cover mt-30">
                    <div class="hero-slider-1 style-4 dot-style-1 dot-style-1-position-1">
                        @foreach ($banner->bannerValues as $index => $item)
                            @php $opacity = $index == 0 ? 1 : 0;@endphp
                            <div class="single-hero-slider single-animation-wrap"
                                 style="background-color:#f4a884;opacity:{{ $opacity }}">
                                <div class="container mx-auto max-w-7xl px-4 flex">
                                    <div class="slider-content">
                                        @if (isset($item->valueDescription))
                                            <h1 class="display-5 text-10 fw-600 mb-10">
                                                {!! $item->valueDescription->title !!}
                                            </h1>
                                            <p class="display-4 text-grey-2 fw-500 mb-30">
                                                {!! $item->valueDescription->content !!}
                                            </p>
                                        @endif
                                        <div class="cta">
                                            {!! $item->link !!}
                                        </div>
                                    </div>
                                    <div class="slider-image">
                                        <img src="{!! $item->image !!}" class="m-auto" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="slider-arrow hero-slider-1-arrow"></div>
                </div>
            </div>
        </section>
    @endforeach
@endsection
