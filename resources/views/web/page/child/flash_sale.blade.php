@if(count($specials))
    <section class="section-padding pb-5">
        <div class="container-xl">
            <div class="section-title">
                <h3 class="">Flash Sale</h3>
            </div>
            <div class="row">
                <div class="col-lg-3 d-none d-lg-flex">
                    <div class="banner-img style-2 wow fadeIn animated">
                        <div class="banner-text">
                            <h2 class="mb-100">Mang thiên nhiên tới ngôi nhà của bạn</h2>
                            <a href="{{ route('product.special') }}" class="btn btn-xs">Mua hàng
                                <i class="fi-rs-arrow-small-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9 col-md-12">
                    <div class="carausel-4-columns-cover arrow-center position-relative">
                        <div class="slider-arrow slider-arrow-2 carausel-4-columns-arrow"
                             id="carausel-4-columns-arrows"></div>
                        <div class="carausel-4-columns carausel-arrow-center" id="carausel-4-columns">
                            @foreach($specials as $product)
                                @include('client.infunstudio.product.structure._product')
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
