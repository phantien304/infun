<section class="popular-categories section-padding">
    <div class="container wow fadeIn animated">
        <div class="section-title">
            <div class="title">
                <h3>Danh mục</h3>
            </div>
            <div class="slider-arrow slider-arrow-2 flex-right carausel-8-columns-arrows"
                 id="categories-8-columns-arrows"></div>
        </div>
        <div class="carausel-8-columns-cover position-relative">
            <div class="carausel-8-columns" id="categories-8-columns">
                @foreach($categories as $item)
                    @if(isset($item->categoryDescription))
                        <div class="card-1">
                            <figure class=" img-hover-scale overflow-hidden">
                                <a href="{!! $item->categoryDescription->getUrlClient() !!}"
                                   title="{!! $item->categoryDescription->title !!}">
                                    <img src="{!! $item->getImageIcon() !!}"
                                         alt="{!! $item->categoryDescription->title !!}">
                                </a>
                            </figure>
                            <h6>
                                <a href="{!! $item->categoryDescription->getUrlClient() !!}">
                                    {!! $item->categoryDescription->title !!}
                                </a>
                            </h6>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</section>
