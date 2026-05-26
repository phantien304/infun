@extends('client.infunstudio.layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}"/>
    <meta property="og:rich_attachment" content="true"/>
    <meta property="og:type" content="article"/>
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}"/>
    <meta property="og:url" itemprop="url" content="{!! $entity->categoryDescription->getUrlClient() !!}"/>
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! resizeImage(getConstant('DEFAULT'), 800, 354, 'client') !!}"/>
    <meta property="og:image:width" content="800"/>
    <meta property="og:image:height" content="354"/>
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title"/>
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description"/>
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $entity->created_at !!}" itemprop="datePublished" name="pubdate"/>
    <meta content="{!! $entity->updated_at !!}" itemprop="dateModified" name="lastmod"/>
    <meta content="{!! $entity->created_at !!}" itemprop="dateCreated"/>
    @include('client.infunstudio.share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary"/>
    <meta name="twitter:url" content="{!! $entity->categoryDescription->getUrlClient() !!}"/>
    <meta name="twitter:title" content="{!! $titleSeo !!}"/>
    <meta name="twitter:description" content="{!! $descriptionSeo !!}"/>
    <meta name="twitter:image" content="{!! resizeImage(getConstant('DEFAULT'), 800, 354, 'client') !!}"/>
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}"/>
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}"/>
    <!-- End Twitter Card -->
    @include('client.infunstudio.share.structure._schema_product_list')
@stop
@section('content')
    @include('client.infunstudio.share.structure._breadcrumb', ['titlePage' => isset($entity->categoryDescription) ? $entity->categoryDescription->title : ''])
    <div class="container-xl mb-30">
        <div class="row flex-row-reverse">
            <div class="col-lg-9">
                <div class="shop-product-fillter">
                    <div class="totall-product">
                        <p>Có <strong class="text-brand">{{ $entities->total() }}</strong> sản phẩm!</p>
                    </div>
                    @include('client.infunstudio.category.structure._sort_by')
                </div>
                <div class="row product-grid">
                    @php $reqFilter = array_get(request()->get('product_filter', []), 'filter_value_id_in', []); @endphp
                    @foreach($entities as $product)
                        @include('client.infunstudio.product.structure._product', ['reqFilter'=>$reqFilter])
                    @endforeach
                </div>
                <div class="pagination-area mt-20 mb-20">
                    <nav aria-label="Phân trang">
                        {!! $entities->links('client.infunstudio.share.structure._paging', ['removeKey' => 'product_category']) !!}
                    </nav>
                </div>
            </div>
            @include('client.infunstudio.category.structure._side_bar')
        </div>
    </div>
@endsection
