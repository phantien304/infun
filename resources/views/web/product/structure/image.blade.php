<div class="row product-image">
    <div class="col-xl-12 detail-gallery" id="product-image">
        <div class="detail-gallery">
            <span class="zoom-icon"><i class="fi-rs-search"></i></span>
            <div class="product-image-slider" data-product-gallery>
                @foreach ($images as $img)
                    <figure class="border-radius-10">
                        <img src="{{ thumbnail($img['image'], 1000, 1000) }}" alt="{{ $img['alt'] ?: $entity->name }}">
                    </figure>
                @endforeach
            </div>
            <div class="slider-nav-thumbnails" data-product-gallery-thumbs>
                @foreach ($images as $img)
                    <div>
                        <img src="{{ thumbnail($img['image'], 147, 147) }}" alt="{{ $img['alt'] ?: $entity->name }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
