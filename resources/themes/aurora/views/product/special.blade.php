@extends('web::layouts.main')

@section('meta')
    @include('web::share.structure._product_listing_meta', [
        'resourceUrl' => route('product.special'),
    ])
@stop

@section('content')
    @include('web::share.structure._product_listing', [
        'titlePage'  => trans('messages.breadcrumbs.special'),
        'totalLabel' => 'sản phẩm khuyến mãi',
    ])
@endsection
