<div class="col-lg-3 primary-sidebar sticky-sidebar @if (isset($class)) {!! $class !!} @endif">
    <div class="widget-area">
        <div class="sidebar-widget-2 widget_search mb-50">
            <div class="search-form">
                <form action="{{ route('blog.getList') }}" method="get">
                    <input type="text" name="blog_description[title_cons]" class="form-control"
                        value="{{ data_get(request()->get('blog_description'), 'title_cons') }}"
                        placeholder="Bạn muốn tìm…">
                    <button type="submit"><i class="fi-rs-search"></i></button>
                </form>
            </div>
        </div>
        @if (count($blogCategories))
            <div class="sidebar-widget widget-category-2 mb-30">
                <h5 class="section-title style-1 mb-30 wow fadeIn animated">Danh mục</h5>
                <ul>
                    @foreach ($blogCategories as $item)
                        @if (!isset($item->title))
                            @continue;
                        @endif
                        <li>
                            <a href="{{ $item->url }}">
                                {!! $item->title !!}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (count($blogTags))
            <div class="sidebar-widget widget-tags mb-50 pb-10">
                <h5 class="section-title style-1 mb-30 wow fadeIn animated">Tags</h5>
                <ul class="tags-list">
                    @foreach ($blogTags as $item)
                        @if (!isset($item->description))
                            @continue;
                        @endif
                        <li class="hover-up">
                            <a href="{{ $item->url }}" title="{!! $item->title !!}"
                                style="background: {{ $item->background }}" class="text-light">
                                <i class="fi-rs-cross mr-10"></i>{!! $item->title !!}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        {{--
        <div class="sidebar-widget product-sidebar  mb-30 p-30 bg-grey border-radius-10">
            <h5 class="section-title style-1 mb-30 wow fadeIn animated">Sản phẩm mới</h5>
            @foreach ($products as $product)
                @if (!isset($product->productDescription))
                    @continue;
                @endif
                @if (count($product->productSpecials))
                    @php
                        $productSpecial = $product->productSpecials->sortByDesc('priority')->first();
                        $discount = round((($product->price - $productSpecial->price) / $product->price) * 100);
                    @endphp
                @endif
                <div class="single-post clearfix">
                    <div class="image">
                        <img src="{!! $product->getImageClient() !!}" alt="{!! $product->productDescription->name !!}">
                    </div>
                    <div class="content pt-10">
                        <h6>
                            <a href="{!! $product->productDescription->getUrlClient() !!}" title="{!! $product->productDescription->name !!}">
                                {!! $product->productDescription->name !!}
                            </a>
                        </h6>
                        @if (count($product->productSpecials))
                            <div class="mb-0 mt-5">
                                <span class="price fs-6">{!! $productSpecial->getPrice() !!} </span>
                                <span class="text-decoration-line-through old-price">
                                    <small>{!! $product->getPrice() !!}</small>
                                </span>
                            </div>
                        @else
                            <p class="price mb-0 mt-5">{!! $product->getPrice() !!}</p>
                        @endif
                        <div class="product-rate">
                            <img src="/client/images/stars-{!! intval(round($product->rating)) !!}.png"
                                alt="{!! $product->total_rating !!} đánh giá" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div> --}}
    </div>
</div>
