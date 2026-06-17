{{--
    Sản phẩm nổi bật — mosaic layout 6 sản phẩm:
        ┌──────────┬─────┬─────┐
        │          │  1  │     │
        │    0     ├─────┤  5  │
        │          │  2  │     │
        ├─────┬────┴─────┼─────┤
        │  3  │     4    │     │
        └─────┴──────────┴─────┘
    Class theme `.card-1`, `.image`, `.title` styled main.css.
    Bootstrap col-md-6 → Tailwind grid 2-col responsive.
--}}
@if(count($features))
    <section class="section-padding product-feature">
        <div class="container mx-auto max-w-7xl px-4">
            <h3 class="mb-30 text-center text-9">Sản phẩm nổi bật</h3>
            @php
                $list = [];
                $key = 0;
                foreach ($features as $i => $product) {
                    $list[$key] = '<div class="card-1">
                                <figure class="image">
                                    <a href="' . $product->url . '"
                                    title="' . $product->name . '" class="flex">
                                        <img src="' . $product->thumbnail(635, 420) . '"
                                             alt="' . $product->name . '">
                                        <h3 class="title p-2 font-xl">' . $product->name . '</h3>
                                    </a>
                                </figure>
                            </div>';
                    $key++;
                }
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-4">
                    @if(filled(data_get($list, 0)))
                        {!! data_get($list, 0) !!}
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            @if(filled(data_get($list, 1)))
                                {!! data_get($list, 1) !!}
                            @endif
                        </div>
                        <div>
                            @if(filled(data_get($list, 2)))
                                {!! data_get($list, 2) !!}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            @if(filled(data_get($list, 3)))
                                {!! data_get($list, 3) !!}
                            @endif
                        </div>
                        <div>
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
