@if(count($manufacturers))
    <section class="manufacturer section-padding">
        <div class="container wow fadeIn animated">
            <div class="section-title">
                <div class="title">
                    <h3>Thương hiệu nổi bật</h3>
                </div>
                <div class="slider-arrow slider-arrow-2 flex-right carausel-8-columns-arrow"
                     id="brands-8-columns-arrows"></div>
            </div>
            <div class="carausel-8-columns-cover position-relative">
                <div class="carausel-8-columns" id="brands-8-columns">
                    @foreach($manufacturers as $item)
                        <div class="card-1">
                            <figure class=" img-hover-scale overflow-hidden">
                                <a href="{!! $item->getUrlClient() !!}" title="{!! $item->name !!}">
                                    <img src="{!! $item->getImageClient() !!}"
                                         alt="{!! $item->name !!}">
                                </a>
                            </figure>
                            <h6>
                                <a href="{!! $item->getUrlClient() !!}" title="{!! $item->name !!}">
                                    {!! $item->name !!}
                                </a>
                            </h6>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
