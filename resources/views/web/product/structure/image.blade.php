<div class="row product-image">
    <div class="col-xl-12 detail-gallery" id="product-image">
        <div class="detail-gallery">
            <span class="zoom-icon"><i class="fi-rs-search"></i></span>
            <div class="product-image-slider">
                @foreach($images as $img)
                    <figure class="border-radius-10">
                        <img src="{{ resizeImage($img['image'], 1000, 1000, 'client') }}"
                             alt="{{ $entity->productDescription->name }}">
                    </figure>
                @endforeach
            </div>
            <div class="slider-nav-thumbnails">
                @foreach($images as $img)
                    <div>
                        <img src="{{ resizeImage($img['image'], 147, 147, 'client') }}"
                             alt="{{ $entity->productDescription->name }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
