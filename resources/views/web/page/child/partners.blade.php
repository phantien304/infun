@if(count($partners))
    <div class="row wrap-partners">
        <div class="col-xl-12 mt-5">
            <div class="content-infunstudio-package">
                <div class="panel-head-infunstudio-package panel-head-general text-center">
                    <div class="row">
                        <div class="col-xl-12 col-sm-10 m-auto">
                            <h2 class="heading-2">CÁC ĐỐI TÁC
                            </h2>
                            <div class="rule"></div>
                            <p class="intro heading-3"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body-product-package">
                <div class="row main-blogs swiper-container">
                    <ul class="swiper-wrapper pb-1">
                        @foreach($partners as $partner)
                            @foreach($partner->bannerValues as $item)
                                @php $title = isset($item->bannerValueDescription) ? $item->bannerValueDescription->title : '';@endphp
                                <li class="swiper-slide col-xl-2 rounded">
                                    @if(filled($item->link))
                                        <a href="{!! $item->link !!}" target="_blank" rel="nofollow"
                                           title="{!! $title !!}">
                                            <img src="{!! $item->image !!}" alt="{!! $title !!}">
                                        </a>
                                    @else
                                        <img src="{!! $item->image !!}" alt="{!! $title !!}">
                                    @endif
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endif
