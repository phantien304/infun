@section('script_header')
    <script type="text/javascript">
        var priceGteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_min', 130000)) }};
        var priceLteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_max', 1000000)) }};
    </script>
@stop

@php
    $hideManufacturer = $hideManufacturer ?? false;
@endphp
<div class="primary-sidebar sticky-sidebar">

    {!! \App\View\FragmentCache::categoryTree() !!}

    <form method="get" action="{{ request()->url() }}">
        @foreach (collect(request()->query())->except(['filter', 'page'])->dot() as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        {!! \App\View\FragmentCache::facets((bool) ($hideManufacturer ?? false)) !!}
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

            // Đánh giá: radio, không phải checkbox — không nằm chung vòng lặp
            // trên được vì cần tick ĐÚNG MỘT ô và ô rỗng ("Tất cả đánh giá")
            // là mặc định. Thiếu đoạn này thì reload xong bộ lọc vẫn áp
            // nhưng sidebar hiện "Tất cả" — người dùng tưởng đã bỏ lọc.
            var rating = p.get('filter[rating_min]');
            if (rating !== null) {
                document.querySelectorAll('input[name="filter[rating_min]"]').forEach(function(rb) {
                    rb.checked = (rb.value === rating);
                });
            }
        }
        // --- Cây danh mục (item 3b): toggle + active + bung nhánh tổ tiên ---
        function openNode(li) {
            var ul = li.querySelector(':scope > .cat-tree-children');
            if (ul) ul.style.display = 'block';
            var caret = li.querySelector(':scope > .cat-tree-row .cat-tree-caret');
            if (caret) caret.style.transform = 'rotate(90deg)';
            var btn = li.querySelector(':scope > .cat-tree-row .cat-tree-toggle');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        }

        function toggleNode(li) {
            var ul = li.querySelector(':scope > .cat-tree-children');
            if (!ul) return;
            var open = ul.style.display !== 'none' && ul.style.display !== '';
            ul.style.display = open ? 'none' : 'block';
            var caret = li.querySelector(':scope > .cat-tree-row .cat-tree-caret');
            if (caret) caret.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
            var btn = li.querySelector(':scope > .cat-tree-row .cat-tree-toggle');
            if (btn) btn.setAttribute('aria-expanded', open ? 'false' : 'true');
        }

        function hydrateTree() {
            var tree = document.querySelector('[data-cat-tree]');
            if (!tree) return;

            // Toggle mở/đóng (thay Alpine cũ) — delegate 1 listener.
            tree.addEventListener('click', function(e) {
                var btn = e.target.closest('.cat-tree-toggle');
                if (!btn || !tree.contains(btn)) return;
                e.preventDefault();
                toggleNode(btn.closest('[data-cat-node]'));
            });

            // Node đang active: khớp filter.category_id hoặc pathname với data-cat-url.
            var catId = new URLSearchParams(location.search).get('filter[category_id]');
            var here = location.pathname.replace(/\/+$/, '');
            var active = null;
            tree.querySelectorAll('[data-cat-node]').forEach(function(li) {
                if (active) return;
                if (catId && li.getAttribute('data-cat-id') === String(catId)) {
                    active = li;
                    return;
                }
                var url = li.getAttribute('data-cat-url');
                if (url) {
                    try {
                        if (new URL(url, location.origin).pathname.replace(/\/+$/, '') === here) active =
                            li;
                    } catch (err) {}
                }
            });
            if (!active) return;

            var row = active.querySelector(':scope > .cat-tree-row');
            if (row) {
                row.classList.remove('border-gray-200', 'hover:border-brand', 'hover:shadow');
                row.classList.add('bg-brand', 'border-brand', 'text-white');
                var link = row.querySelector('.cat-tree-link');
                if (link) {
                    link.classList.remove('text-gray-800');
                    link.classList.add('text-white', 'font-semibold', 'hover:text-white');
                }
            }
            // Bung mọi nhánh tổ tiên để node active hiện ra.
            var parent = active.parentElement ? active.parentElement.closest('[data-cat-node]') : null;
            while (parent) {
                openNode(parent);
                parent = parent.parentElement ? parent.parentElement.closest('[data-cat-node]') : null;
            }
        }

        function init() {
            hydrateFacets();
            hydrateTree();
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
