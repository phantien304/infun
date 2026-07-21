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
            {{-- Product grid: 1 col mobile, 2 sm, 3 md, 4 lg.
                 `product-grid` is kept so legacy slick/animation hooks bind. --}}
            <div class="product-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-5">
                @foreach ($entities as $product)
                    @include('web::product.structure._product')
                @endforeach
            </div>
            <div class="pagination-area mt-20 mb-20">
                <nav aria-label="Phân trang">
                    {{-- {!! $entities->links('web::share.structure._paging') !!} --}}
                    @if ($entities->currentPage() > 1 || $entities->hasMorePages())
                        <ul class="pagination flex flex-wrap items-center gap-2 justify-center list-none p-0 m-0">
                            @if ($entities->currentPage() > 1)
                                <li class="page-item">
                                    <a class="page-link" href="{{ $entities->previousPageUrl() }}" rel="prev"
                                        title="Trang trước">‹ Trước</a>
                                </li>
                            @endif
                            <li class="page-item active"><span class="page-link">Trang
                                    {{ $entities->currentPage() }}</span></li>
                            @if ($entities->hasMorePages())
                                <li class="page-item">
                                    <a class="page-link" href="{{ $entities->nextPageUrl() }}" rel="next"
                                        title="Trang sau">Sau ›</a>
                                </li>
                            @endif
                        </ul>
                    @endif
                </nav>
            </div>
        </div>
        <div class="lg:col-span-1 lg:order-2 order-1">
            @include('web::category.structure._side_bar', ['hideManufacturer' => $hideManufacturer])
        </div>
    </div>
</div>
