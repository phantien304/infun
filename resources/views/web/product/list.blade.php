@php
    $category = request()->get('category_id_eq') ?? '';
    $sortField = request()->get('sort_field') ?? '';
    $sortType = request()->get('sort_type') ?? '';
    $start = $entities->total() > 0 ? ($entities->currentPage() - 1) * $entities->perPage() + 1 : 0;
    if ($entities->currentPage() == $entities->lastPage()) {
        $last = $entities->total();
    } else {
        $last = $entities->currentPage() * $entities->perPage();
    }
@endphp
@extends('web.layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! route('product.getList') !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! thumbnail(getModuleConfig('img_default'), 800, 354) !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    @include('web.share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! route('product.getList') !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! thumbnail(getModuleConfig('img_default'), 800, 354) !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    @include('web.share.structure._schema_product_list')
@stop
@section('content')
    @include('web.share.structure._breadcrumb', ['titlePage' => 'Sản phẩm'])
    <div class="container-xl mb-30">
        <div class="row flex-row-reverse">
            <div class="col-lg-12 mt-3 mb-30">
                @if (session()->has('success'))
                    <div class="alert alert-success">{!! session()->get('success') !!}</div>
                @endif
                @if (session()->has('failed'))
                    <div class="alert alert-danger">{!! session()->get('failed') !!}</div>
                @endif
            </div>
            <div class="col-lg-9">
                <div class="shop-product-fillter">
                    <div class="totall-product">
                        <p>Có <strong class="text-brand">{{ $entities->total() }}</strong> sản phẩm!</p>
                    </div>
                    @include('web.product.structure._sort_by')
                </div>
                <div class="row product-grid">
                    @php $reqFilter = data_get(request()->get('product_filter', []), 'filter_value_id_in', []); @endphp
                    @foreach ($entities as $product)
                        @include('web.product.structure._product', ['reqFilter' => $reqFilter])
                    @endforeach
                </div>
                <div class="pagination-area mt-20 mb-20">
                    <nav aria-label="Phân trang">
                        {!! $entities->links('web.share.structure._paging') !!}
                    </nav>
                </div>
            </div>
            @include('web.category.structure._side_bar')
        </div>
    </div>
@endsection
