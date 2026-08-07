@extends('web::layouts.main')

@section('meta')
    @include('web::share.structure._product_listing_meta', [
        'resourceUrl'   => $entity->url,
        'resourceImage' => $entity->thumbnail(800, 354),
    ])
@stop

@section('content')
    @include('web::share.structure._product_listing', [
        'titlePage'        => $entity->name,
        'hideManufacturer' => true,
    ])
@endsection
