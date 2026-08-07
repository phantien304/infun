@section('banner')
    @foreach($banners as $banner)
        <section class="home-slider relative mb-30">
            <div class="container mx-auto max-w-7xl px-4">
                <div class="home-slide-cover mt-30">
                    <div class="hero-slider-1 style-4 dot-style-1 dot-style-1-position-1">
                        @foreach($banner->bannerValues as $index => $item)
                            <div class="single-hero-slider single-animation-wrap"
                                 style="background-image: url({{ asset($item->image) }});">
                                <div class="slider-content">
                                    @if(isset($item->bannerValueDescription))
                                        <h1 class="display-2 mb-40">
                                            {!! $item->bannerValueDescription->title !!}
                                        </h1>
                                        <p class="mb-65">
                                            {!! $item->bannerValueDescription->content !!}
                                        </p>
                                    @endif
                                    <form class="form-subcriber flex gap-2">
                                        <input type="email" placeholder="Nhập email của bạn" class="form-control flex-1">
                                        <button class="btn" type="submit">Đăng&nbsp;Ký</button>
                                    </form>
                                    <p class="noti-subcriber mt-10 ps-4 text-danger" style="font-size: 15px;"></p>
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
