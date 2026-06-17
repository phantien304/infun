{{--
    Category landing page. See web::share.structure._product_listing for
    the shared listing skeleton. Controller scopes the product query by
    category_id through a closure (no request filter), so the sidebar
    category facet still lights up via its own URL comparison.
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
        'titlePage' => $entity->title,
    ])
@endsection
