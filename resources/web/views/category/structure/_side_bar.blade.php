@section('script_header')
    <script type="text/javascript">
        var priceGteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_min', 130000)) }};
        var priceLteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_max', 1000000)) }};
    </script>
@stop

@php
    $currentCategoryId = request()->input('filter.category_id');
    $hideManufacturer = $hideManufacturer ?? false;
    $byParent = collect();
    $byId = collect();
    foreach ($categories ?? [] as $cat) {
        $byId->put($cat->id, $cat);
        $byParent->put($cat->parent_id, ($byParent->get($cat->parent_id) ?? collect())->push($cat));
    }
    $rootCategories = $byParent->get(0) ?? collect();
    $openIds = [];
    $activeId = null;
    foreach ($categories ?? [] as $cat) {
        if ((int) $currentCategoryId === (int) $cat->id || $cat->url === request()->url()) {
            $activeId = (int) $cat->id;
            $cursor = $cat;
            while ($cursor && (int) $cursor->parent_id !== 0) {
                $openIds[(int) $cursor->parent_id] = true;
                $cursor = $byId->get((int) $cursor->parent_id);
            }
            break;
        }
    }
@endphp
<div class="primary-sidebar sticky-sidebar">
    @if ($rootCategories->isNotEmpty())
        <div class="sidebar-widget mb-30">
            <h5 class="section-title style-1 mb-30 wow fadeIn animated">Danh mục</h5>
            <ul class="category-tree list-none m-0 p-0">
                @foreach ($rootCategories as $root)
                    @include('web::category.structure._side_bar_node', [
                        'node' => $root,
                        'byParent' => $byParent,
                        'openIds' => $openIds,
                        'activeId' => $activeId,
                        'depth' => 0,
                    ])
                @endforeach
            </ul>
        </div>
    @endif

    <form method="get" action="{{ request()->url() }}">
        @foreach (collect(request()->query())->except(['filter', 'page'])->dot() as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        {!! \App\Services\View\FragmentCache::facets((bool) ($hideManufacturer ?? false)) !!}
    </form>

    <div class="sidebar-widget product-sidebar mb-30 p-30 bg-grey border-radius-10">
        <h5 class="section-title style-1 mb-30 wow fadeIn animated">Sản phẩm mới</h5>
        @foreach ($latestProducts as $product)
            <div class="single-post flex gap-3 mb-3 clearfix">
                <div class="image flex-shrink-0">
                    <img src="{{ $product->thumbnail(50, 50) }}" alt="{{ $product->name }}">
                </div>
                <div class="content pt-10">
                    <h6>
                        <a href="{!! $product->url !!}" title="{!! $product->name !!}">
                            {!! $product->name !!}
                        </a>
                    </h6>
                    @if (filled($product->productVariantSpecial))
                        <div class="mb-0 mt-5">
                            <span class="price fs-6">{!! $product->productVariantSpecial->pricePromotionLabel !!}</span>
                            <span class="text-decoration-line-through old-price">
                                <small>{!! $product->productVariantSpecial->priceRegularLabel !!}</small>
                            </span>
                        </div>
                    @else
                        <p class="price mb-0 mt-5">{!! $product->priceLabel !!}</p>
                    @endif
                    <div class="product-rate">
                        <img src="/web/images/stars-{!! intval(round($product->ratingAvg)) !!}.png"
                            alt="{!! $product->reviewCount !!} đánh giá" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    (function() {
        function hydrateFacets() {
            var p = new URLSearchParams(location.search);

            var kw = p.get('filter[keyword]');
            if (kw !== null) {
                var kwEl = document.getElementById('filter-keyword');
                if (kwEl) kwEl.value = kw;
            }

            ['filter[in_stock][]', 'filter[manufacturer_id][]', 'filter[filter_value_id][]'].forEach(function(
                name) {
                var selected = p.getAll(name);
                if (!selected.length) return;
                document.querySelectorAll('input[name="' + name + '"]').forEach(function(cb) {
                    if (selected.indexOf(cb.value) !== -1) cb.checked = true;
                });
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hydrateFacets);
        } else {
            hydrateFacets();
        }
    })();
</script>
