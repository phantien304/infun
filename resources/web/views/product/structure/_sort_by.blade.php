{{--
    Sort + per-page dropdowns. Class theme `.sort-by-product-area`,
    `.sort-by-cover`, `.sort-by-dropdown` styled main.css với hover CSS để
    show/hide dropdown — KHÔNG cần Alpine. Tailwind chỉ thêm gap + flex
    wrapper cho 2 group nằm cạnh.
--}}
<div class="sort-by-product-area flex items-center gap-3 flex-wrap">
    <div class="sort-by-cover relative mr-10">
        <div class="sort-by-product-wrap flex items-center gap-1">
            <div class="sort-by"><span><i class="fi-rs-apps"></i>Hiển thị:</span></div>
            <div class="sort-by-dropdown-wrap">
                <span>{{ request()->get('per_page', 20) }}<i class="fi-rs-angle-small-down"></i></span>
            </div>
        </div>
        <div class="sort-by-dropdown">
            <ul>
                @foreach ($perPageMenu as $item)
                    <li>
                        <a class="@if ($item['active']) active @endif" href="{{ $item['url'] }}">
                            {{ $item['value'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="sort-by-cover relative">
        <div class="sort-by-product-wrap flex items-center gap-1">
            <div class="sort-by"><span><i class="fi-rs-apps-sort"></i>Sắp xếp theo:</span></div>
            @php
                $currentSort = collect($sortMenu)->firstWhere('active', true) ?? ($sortMenu[0] ?? null);
            @endphp
            <div class="sort-by-dropdown-wrap">
                <span>{{ $currentSort['label'] ?? trans('sort.-created_at') }}<i class="fi-rs-angle-small-down"></i></span>
            </div>
        </div>
        <div class="sort-by-dropdown">
            <ul>
                @foreach ($sortMenu as $item)
                    <li>
                        <a class="@if ($item['active']) active @endif" href="{{ $item['url'] }}" title="{{ $item['label'] }}">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
