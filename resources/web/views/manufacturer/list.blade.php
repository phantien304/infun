{{--
    Manufacturer landing page. Reuses the shared listing partials so the
    visual layout stays identical to category / product / special pages.
    Only the canonical URL, OG image and breadcrumb title differ.
--}}
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
