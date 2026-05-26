<div class="row product-image">
    <div class="col-xl-12" id="product-image">
        <div class="col-big-img" id="sys_center_img">
            <div class="image col-lg-12 col-md-12">
                @if (filled($discount))
                    <span class="product-label product-label-special">
                    <span>-{{ $discount }}</span><label>%</label>
                </span>
                @endif
                <div class="cell-center sys_show_img_big">
                    <img src="{{ resizeImage($entity->image, 540, 540, 'client') }}"
                         title="{{ $entity->productDescription->name }}"
                         alt="{{ $entity->productDescription->name }}" id="v7_BD_ZoomImg"
                         data-zoom-image="{{ resizeImage($entity->image, 540, 540, 'client') }}"
                         class="sys_img_big img-responsive">
                </div>
            </div>
        </div>
        <div id="sys_col_list_thumb" class="col-list-thumb">
            @foreach($images as $img)
                <div class="wrap-thumb sys_show_img_big">
                    <a class="v7_DB_Imgbor" href="javascript:void(0);"
                       data-image="{{ resizeImage($img['image'], 540, 540, 'client') }}"
                       data-zoom-image="{{ resizeImage($img['image'], 540, 540, 'client') }}">
                        <img data-img-big="{{ resizeImage($img['image'], 540, 540, 'client') }}"
                             data-zoom-image="{{ resizeImage($img['image'], 540, 540, 'client') }}"
                             src="/client/images/null.gif" alt="{{ $entity->productDescription->name }}"
                             style="background-image: url({{ resizeImage($img['image'], 540, 540, 'client') }})">
                    </a>
                </div>
            @endforeach
            <div id="sys_btn_slide_thumbs" class="btn-slide-thumbs">
                <span class="fas fa-angle-right"></span>
            </div>
        </div>
    </div>
    <div class="col-xl-12 usage d-none d-xl-block">
        <div class="row">
            <div class="col-xl-12">
                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <span><i class="fa fa-asterisk text-danger"></i>&nbsp;Công dụng chính</span>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush ingredients">
                            @foreach($totalEffects as $key => $item)
                                <li class="list-group-item">
                                    @if(empty(array_get($item, 'icon')))
                                        <img class="icon-effect"
                                             alt="{!! array_get($item, 'name') !!}"
                                             src="{!! array_get($item, 'image_icon') !!}">
                                    @else
                                        <i class="{{ array_get($item, 'icon') }} text-secondary"></i>
                                    @endif
                                    &nbsp{{ array_get($item, 'name') }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
