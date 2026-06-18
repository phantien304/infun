@extends('web::layouts.main')

@section('meta')
    @include('web::share.structure._product_listing_meta', [
        'resourceUrl' => route('product.getList'),
    ])
@stop

@section('content')
    @include('web::share.structure._product_listing', [
        'titlePage' => 'Sản phẩm',
    ])
@endsection
