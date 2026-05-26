@if(count($features))
    <section class="section-padding product-feature">
        <div class="container-xl">
            <h3 class="mb-30 text-center text-9">Sản phẩm nổi bật</h3>
            <?php
            $list = [];
            $key = 0;
            foreach ($features as $i => $product) {
                $list[$key] = '<div class="card-1">
                            <figure class="image">
                                <a href="' . $product->url . '"
                                title="' . $product->name . '" class="d-flex">
                                    <img src="' . $product->thumbnail(635, 420) . '"
                                         alt="' . $product->name . '">
                                    <h3 class="title p-2 font-xl">' . $product->name . '</h3>
                                </a>
                            </figure>
                        </div>';
                $key++;
            }
            ?>
            <div class="row">
                <div class="col-md-6 col-sm-6 col-xs-12">
                    @if(filled(data_get($list, 0)))
                        {!! data_get($list, 0) !!}
                    @endif
                    <div class="row">
                        <div class="col-md-6">
                            @if(filled(data_get($list, 1)))
                                {!! data_get($list, 1) !!}
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if(filled(data_get($list, 2)))
                                {!! data_get($list, 2) !!}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-sm-6 col-xs-12">
                    <div class="row">
                        <div class="col-md-6">
                            @if(filled(data_get($list, 3)))
                                {!! data_get($list, 3) !!}
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if(filled(data_get($list, 4)))
                                {!! data_get($list, 4) !!}
                            @endif
                        </div>
                    </div>
                    @if(filled(data_get($list, 5)))
                        {!! data_get($list, 5) !!}
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
