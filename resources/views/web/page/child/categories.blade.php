@php $first = 0; @endphp
<section class="product-tabs section-padding position-relative wow fadeIn animated">
    <div class="container-xl">
        <div class="section-title style-2">
            <h3>Sản phẩm phổ biến</h3>
            <ul class="nav nav-tabs links" id="myTab" role="tablist">
                @foreach($categoriesHomepage as $id => $item)
                    @if(!isset($item['category']->categoryDescription))
                        @continue;
                    @endif
                    @if(count($item['product']))
                        @php if(!$first){ $first = $id; }@endphp
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if($first == $id) active @endif" id="nav-tab-{{ $id }}"
                                    data-bs-toggle="tab"
                                    data-bs-target="#tab-{{ $id }}"
                                    type="button" role="tab" aria-controls="tab-{{ $id }}" aria-selected="false">
                                {!! $item['category']->categoryDescription->title !!}
                            </button>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
        <div class="tab-content wow fadeIn animated" id="myTabContent">
            @foreach($categoriesHomepage as $id => $item)
                @if(count($item['product']))
                    <div class="tab-pane fade @if($first == $id) show active @endif" id="tab-{{ $id }}" role="tabpanel"
                         aria-labelledby="tab-{{ $id }}">
                        <div class="row product-grid-4">
                            @foreach($item['product'] as $product)
                                @include('client.infunstudio.product.structure._product')
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>

