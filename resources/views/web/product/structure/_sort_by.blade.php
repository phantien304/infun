<div class="sort-by-product-area">
    <div class="sort-by-cover mr-10">
        <div class="sort-by-product-wrap">
            <div class="sort-by">
                <span><i class="fi-rs-apps"></i>Hiển thị:</span>
            </div>
            <div class="sort-by-dropdown-wrap">
                <span> {!! request()->get('per_page') ?? 20 !!}<i class="fi-rs-angle-small-down"></i></span>
            </div>
        </div>
        <div class="sort-by-dropdown">
            <ul>
                <li>
                    <a class="@if(request()->get('per_page') == 50) active @endif"
                       href="{{ route('product.getList', array_merge(request()->except('page'),['per_page' => 50])) }}">
                        50
                    </a>
                </li>
                <li>
                    <a class="@if(request()->get('per_page') == 100) active @endif"
                       href="{{ route('product.getList', array_merge(request()->except('page'),['per_page' => 100])) }}">
                        100
                    </a>
                </li>
                <li>
                    <a class="@if(request()->get('per_page') == 150) active @endif"
                       href="{{ route('product.getList', array_merge(request()->except('page'),['per_page' => 150])) }}">
                        150
                    </a>
                </li>
                <li>
                    <a class="@if(request()->get('per_page') == 200) active @endif"
                       href="{{ route('product.getList', array_merge(request()->except('page'),['per_page' => 200])) }}">
                        200
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div class="sort-by-cover">
        <div class="sort-by-product-wrap">
            <div class="sort-by">
                <span><i class="fi-rs-apps-sort"></i>Sắp xếp theo:</span>
            </div>
            @php
                $sortField = request()->get('sort_field');
                $sortType = request()->get('sort_type');
                $textSortBy = 'Mới nhất';
                if(filled($sortField) && filled($sortType)){
                    $textSortBy = getInfunStudioConfig('sort_by.'.$sortField.'.'.$sortType);
                }
            @endphp
            <div class="sort-by-dropdown-wrap">
                <span> {!! $textSortBy !!} <i class="fi-rs-angle-small-down"></i></span>
            </div>
        </div>
        <div class="sort-by-dropdown">
            <ul>
                <li>
                    <a class="@if($textSortBy == 'Mới nhất') active @endif"
                       href="{{ route('product.getList', array_merge(request()->all(), ['sort_field' => 'created_at', 'sort_type' => 'DESC'])) }}">
                        Mới nhất
                    </a>
                </li>
                <li>
                    <a class="@if($sortField == 'created_at' && $sortType == 'ASC') active @endif"
                       href="{{ route('product.getList', array_merge(request()->all(), ['sort_field' => 'created_at', 'sort_type' => 'ASC'])) }}">
                        Cũ nhất
                    </a>
                </li>
                <li>
                    <a class="@if($sortField == 'price' && $sortType == 'ASC') active @endif"
                       title="Giá tăng dần"
                       href="{{ route('product.getList', array_merge(request()->all(), ['sort_field' => 'price', 'sort_type' => 'ASC'])) }}">
                        Giá tăng dần
                    </a>
                </li>
                <li>
                    <a class="@if($sortField == 'price' && $sortType == 'DESC') active @endif"
                       title="Giá giảm dần"
                       href="{{ route('product.getList', array_merge(request()->all(), ['sort_field' => 'price', 'sort_type' => 'DESC'])) }}">
                        Giá giảm dần
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
