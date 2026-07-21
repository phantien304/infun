@php
    $totalLabel = $totalLabel ?? 'sản phẩm';
    $hideManufacturer = $hideManufacturer ?? false;
@endphp

@include('web::share.structure._breadcrumb', ['titlePage' => $titlePage])
<div class="container mx-auto max-w-7xl px-4 mb-30">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-4 mt-3 mb-30">
            @if (session()->has('success'))
                <div class="alert alert-success">{!! session()->get('success') !!}</div>
            @endif
            @if (session()->has('failed'))
                <div class="alert alert-danger">{!! session()->get('failed') !!}</div>
            @endif
        </div>
        <div class="lg:col-span-3 lg:order-1 order-2">
            <div class="shop-product-fillter flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                {{--
                <div class="totall-product">
                    <p>Có <strong class="text-brand">{{ $entities->total() }}</strong> {{ $totalLabel }}!</p>
                </div>
                --}}
                @include('web::product.structure._sort_by')
            </div>
            <div class="product-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-5">
                @foreach ($entities as $product)
                    @include('web::product.structure._product')
                @endforeach
            </div>
            <div class="pagination-area mt-20 mb-20">
                <nav aria-label="Phân trang">
                    {!! $entities->links('web::share.structure._paging') !!}
                </nav>
            </div>
        </div>
        <div class="lg:col-span-1 lg:order-2 order-1">
            @include('web::category.structure._side_bar', ['hideManufacturer' => $hideManufacturer])
        </div>
    </div>
</div>
