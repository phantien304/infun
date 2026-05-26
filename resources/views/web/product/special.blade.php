@extends('client.infunstudio.layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}"/>
    <meta property="og:rich_attachment" content="true"/>
    <meta property="og:type" content="article"/>
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}"/>
    <meta property="og:url" itemprop="url" content="{!! route('product.special') !!}"/>
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! resizeImage(getConstant('DEFAULT'), 800, 354, 'client') !!}"/>
    <meta property="og:image:width" content="800"/>
    <meta property="og:image:height" content="354"/>
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title"/>
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description"/>
    <!-- END META FOR FACEBOOK -->
    @include('client.infunstudio.share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary"/>
    <meta name="twitter:url" content="{!! route('product.special') !!}"/>
    <meta name="twitter:title" content="{!! $titleSeo !!}"/>
    <meta name="twitter:description" content="{!! $descriptionSeo !!}"/>
    <meta name="twitter:image" content="{!! resizeImage(getConstant('DEFAULT'), 800, 354, 'client') !!}"/>
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}"/>
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}"/>
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@context":"http://schema.org","@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    @foreach($productSpecials as $product)
        @if(isset($product->productDescription))
        <script type="application/ld+json">
            {"@context":"https://schema.org/","@type":"Product","url":"{!! $product->productDescription->getUrlClient() !!}","image":"{!! $product->getImageClient(540, 540) !!}","name":"{!! $product->productDescription->name !!}","description":"{!! $product->productDescription->description !!}","sku":"{!! $product->sku !!}","aggregateRating":{"@type":"AggregateRating","ratingValue":"{!! $product->rating !!}","reviewCount":"{!! $product->total_rating ?? 0 !!}"},"offers":{"@type":"Offer","url":"{!! $product->productDescription->getUrlClient() !!}","itemCondition":"https://schema.org/NewCondition","availability":"https://schema.org/InStock","priceCurrency":"VND","price":{!! $product->price !!}}}
        </script>
        @endif
    @endforeach
    <script type="application/ld+json">
        {"@context":"http://schema.org","@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
    </script>
@stop
@section('content')
    @include('client.infunstudio.share.structure._breadcrumb', ['titlePage' => 'Sản phẩm khuyến mãi'])
    <div class="container-xl mb-30">
        <div class="row flex-row-reverse">
            <div class="col-lg-9">
                <div class="shop-product-fillter">
                    <div class="totall-product">
                        <p>Có <strong class="text-brand">{{ $entities->total() }}</strong> sản phẩm!</p>
                    </div>
                    @include('client.infunstudio.product.structure._sort_by_special')
                </div>
                <div class="row product-grid">
                    @php $reqFilter = array_get(request()->get('product_filter', []), 'filter_value_id_in', []); @endphp
                    @foreach($productSpecials as $product)
                        @include('client.infunstudio.product.structure._product', ['reqFilter' => $reqFilter])
                    @endforeach
                </div>
                <div class="pagination-area mt-20 mb-20">
                    <nav aria-label="Phân trang">
                        {!! $entities->links('client.infunstudio.share.structure._paging') !!}
                    </nav>
                </div>
            </div>
            @include('client.infunstudio.category.structure._side_bar_special')
        </div>
    </div>
@endsection
