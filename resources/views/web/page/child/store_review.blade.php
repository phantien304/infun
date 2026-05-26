@if (count($storeReviews))
    <section class="store-review section-padding">
        <div class="container-xl wow fadeIn animated">
            <h3 class="mb-30 text-center text-9">Khách hàng của In&Fun</h3>
            <div class="carausel-5-columns-cover arrow-center position-relative">
                <div class="slider-arrow slider-arrow-2 carausel-5-columns-arrow" id="review-5-columns-arrows"></div>
                <div class="carausel-5-columns" id="review-5-columns">
                    @foreach ($storeReviews as $item)
                        <div class="card-1">
                            <figure class="img-hover-scale overflow-hidden">
                                <a href="{!! $item->url !!}" title="{!! $item->name !!}">
                                    <img src="{!! $item->thumbnail(312, 340) !!}" alt="{!! $item->name !!}">
                                    <div class="author-review">
                                        <i class="fab fa-{{ $item->social_icon }}"></i>
                                        <span>by {!! $item->name !!}</span>
                                    </div>
                                </a>
                            </figure>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
