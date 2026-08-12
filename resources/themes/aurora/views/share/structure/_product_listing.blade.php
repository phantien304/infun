@php
    $totalLabel = $totalLabel ?? 'sản phẩm';
    $hideManufacturer = $hideManufacturer ?? false;

    $filterInput = (array) request()->input('filter', []);

    $buildUrlWithoutFilter = function (array $keys, array $valueToRemove = []) {
        $filter = (array) request()->input('filter', []);
        foreach ($keys as $key) {
            if (array_key_exists($key, $valueToRemove) && isset($filter[$key]) && is_array($filter[$key])) {
                $filter[$key] = array_values(array_diff($filter[$key], [(string) $valueToRemove[$key]]));
                if (empty($filter[$key])) {
                    unset($filter[$key]);
                }
                continue;
            }
            unset($filter[$key]);
        }

        $query = request()->except('page');
        if (empty($filter)) {
            unset($query['filter']);
        } else {
            $query['filter'] = $filter;
        }

        return request()->url() . (empty($query) ? '' : '?' . http_build_query($query));
    };

    $clearAllQuery = collect(request()->query())->except(['filter', 'page'])->all();
    $clearAllUrl = request()->url() . (empty($clearAllQuery) ? '' : '?' . http_build_query($clearAllQuery));

    $activeFilters = [];

    $keyword = trim((string) ($filterInput['keyword'] ?? ''));
    if ($keyword !== '') {
        $activeFilters[] = ['label' => '"' . $keyword . '"', 'url' => $buildUrlWithoutFilter(['keyword'])];
    }

    $priceMinRaw = $filterInput['price_min'] ?? null;
    $priceMaxRaw = $filterInput['price_max'] ?? null;
    $priceMin = filled($priceMinRaw) ? (int) preg_replace('/[^\d]/', '', (string) $priceMinRaw) : null;
    $priceMax = filled($priceMaxRaw) ? (int) preg_replace('/[^\d]/', '', (string) $priceMaxRaw) : null;
    if ($priceMin || $priceMax) {
        $priceLabel = $priceMin && $priceMax
            ? money($priceMin) . ' – ' . money($priceMax)
            : ($priceMin ? 'Từ ' . money($priceMin) : 'Đến ' . money($priceMax));
        $activeFilters[] = ['label' => $priceLabel, 'url' => $buildUrlWithoutFilter(['price_min', 'price_max'])];
    }

    $ratingMin = $filterInput['rating_min'] ?? null;
    if (filled($ratingMin)) {
        $activeFilters[] = [
            'label' => str_repeat('★', (int) $ratingMin) . ' trở lên',
            'url' => $buildUrlWithoutFilter(['rating_min']),
        ];
    }

    foreach ((array) ($filterInput['in_stock'] ?? []) as $stockValue) {
        $activeFilters[] = [
            'label' => $stockValue === '1' ? 'Còn hàng' : 'Hết hàng',
            'url' => $buildUrlWithoutFilter(['in_stock'], ['in_stock' => $stockValue]),
        ];
    }

    $selectedManufacturerIds = (array) ($filterInput['manufacturer_id'] ?? []);
    if (! empty($selectedManufacturerIds)) {
        $manufacturerNames = \App\Data\Output\ManufacturerDTO::collect(
            app(\App\Repositories\Interfaces\ManufacturerRepositoryInterface::class)->listAllCached()
        )->keyBy('id');
        foreach ($selectedManufacturerIds as $manufacturerId) {
            $activeFilters[] = [
                'label' => $manufacturerNames->get((int) $manufacturerId)?->name ?? '#' . $manufacturerId,
                'url' => $buildUrlWithoutFilter(['manufacturer_id'], ['manufacturer_id' => $manufacturerId]),
            ];
        }
    }

    $selectedFilterValueIds = (array) ($filterInput['filter_value_id'] ?? []);
    if (! empty($selectedFilterValueIds)) {
        $filterValueNames = \App\Data\Output\FilterDTO::collect(
            app(\App\Repositories\Interfaces\FilterRepositoryInterface::class)->listAllCached()
        )->flatMap(fn ($filter) => $filter->filterValues)->keyBy('id');
        foreach ($selectedFilterValueIds as $filterValueId) {
            $activeFilters[] = [
                'label' => $filterValueNames->get((int) $filterValueId)?->name ?? '#' . $filterValueId,
                'url' => $buildUrlWithoutFilter(['filter_value_id'], ['filter_value_id' => $filterValueId]),
            ];
        }
    }
@endphp

@section('style')
    <style>[x-cloak] { display: none !important; }</style>
@stop

<div class="mx-auto max-w-[1320px] px-5" x-data="{ drawerOpen: false }">

    <nav class="py-4 text-[13px] text-ink-2 flex items-center gap-2 flex-wrap">
        @foreach ($breadcrumbs as $i => $breadcrumb)
            @if ($i > 0)
                <span class="text-ink-3">/</span>
            @endif
            @if (filled($breadcrumb['href']) && $i < count($breadcrumbs) - 1)
                <a href="{{ $breadcrumb['href'] }}" class="hover:text-ink">{{ $breadcrumb['text'] }}</a>
            @else
                <span class="text-ink">{{ $breadcrumb['text'] }}</span>
            @endif
        @endforeach
    </nav>

    <div class="pb-5">
        <h1 class="text-[28px] lg:text-[34px] font-extrabold tracking-tight">{{ $titlePage }}</h1>
        <p class="text-[14px] text-ink-2 mt-1.5">
            <span class="font-semibold text-ink tabular-nums">{{ number_format($entities->total()) }}</span>
            {{ $totalLabel }}
        </p>
    </div>

    <div class="grid grid-cols-12 gap-6 lg:gap-8 pb-12">

        <div x-show="drawerOpen" x-cloak x-transition.opacity class="lg:hidden fixed inset-0 bg-ink/50 z-[70]"
            @click="drawerOpen = false"></div>
        <aside class="fixed inset-y-0 left-0 z-[80] w-[86vw] max-w-sm bg-canvas p-5 overflow-y-auto -translate-x-full transition-transform duration-200
                       lg:static lg:col-span-3 lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 lg:bg-transparent lg:p-0
                       lg:sticky lg:top-[88px] lg:self-start"
            :class="drawerOpen && '!translate-x-0'">
            <div class="lg:hidden flex items-center justify-between mb-4">
                <p class="text-[17px] font-bold">Bộ lọc</p>
                <button @click="drawerOpen = false" class="w-9 h-9 rounded-full hover:bg-white grid place-items-center"
                    aria-label="Đóng">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
            @include('web::category.structure._side_bar', ['hideManufacturer' => $hideManufacturer])
        </aside>

        <div class="col-span-12 lg:col-span-9">
            @if (session()->has('success'))
                <div class="mb-4 rounded-xl bg-mint-tint text-mint px-4 py-3 text-[14px]">{!! session('success') !!}</div>
            @endif
            @if (session()->has('failed'))
                <div class="mb-4 rounded-xl bg-brand-tint text-brand px-4 py-3 text-[14px]">{!! session('failed') !!}</div>
            @endif

            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <button @click="drawerOpen = true"
                    class="lg:hidden h-10 px-4 rounded-xl bg-white border border-line font-medium text-[14px] flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 6h16M7 12h10M10 18h4" />
                    </svg>
                    <span>Bộ lọc</span>
                    @if (count($activeFilters) > 0)
                        <span
                            class="w-5 h-5 rounded-full bg-brand text-white text-[11px] font-bold grid place-items-center">{{ count($activeFilters) }}</span>
                    @endif
                </button>

                <p class="hidden sm:block text-[13.5px] text-ink-2">
                    Đang xem
                    <span class="font-semibold text-ink tabular-nums">{{ $entities->firstItem() ?? 0 }}–{{ $entities->lastItem() ?? 0 }}</span>
                    trong
                    <span class="font-semibold text-ink tabular-nums">{{ number_format($entities->total()) }}</span>
                </p>

                <div class="ml-auto">
                    @include('web::product.structure._sort_by')
                </div>
            </div>

            @if (count($activeFilters) > 0)
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    @foreach ($activeFilters as $chip)
                        <a href="{{ $chip['url'] }}"
                            class="h-8 pl-3 pr-2 rounded-full bg-white border border-line hover:border-ink text-[13px] font-medium flex items-center gap-1.5 transition">
                            <span>{{ $chip['label'] }}</span>
                            <svg class="w-3.5 h-3.5 text-ink-3" fill="none" stroke="currentColor" stroke-width="2.5"
                                viewBox="0 0 24 24">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </a>
                    @endforeach
                    <a href="{{ $clearAllUrl }}"
                        class="h-8 px-3 rounded-full text-[13px] font-semibold text-brand hover:bg-brand-tint transition">Xoá
                        tất cả</a>
                </div>
            @endif

            @if ($entities->isEmpty())
                <div class="rounded-3xl bg-white border border-line py-20 text-center">
                    <svg class="w-12 h-12 mx-auto text-ink-3 mb-4" fill="none" stroke="currentColor" stroke-width="1.5"
                        viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                    <p class="text-[17px] font-semibold">Không tìm thấy sản phẩm nào</p>
                    <p class="text-[14px] text-ink-2 mt-1.5">Thử bỏ bớt bộ lọc hoặc đổi từ khoá tìm kiếm.</p>
                    <a href="{{ $clearAllUrl }}"
                        class="mt-5 inline-flex h-10 px-5 rounded-xl bg-ink text-white text-[14px] font-semibold hover:bg-ink-2 transition items-center">Xoá
                        tất cả</a>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach ($entities as $product)
                        @include('web::product.structure._product')
                    @endforeach
                </div>

                <div class="pagination-area mt-8">
                    <nav aria-label="Phân trang">
                        {!! $entities->links('web::share.structure._paging') !!}
                    </nav>
                </div>
            @endif
        </div>
    </div>
</div>
