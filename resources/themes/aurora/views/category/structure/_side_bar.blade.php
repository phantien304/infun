@section('script_header')
    <script type="text/javascript">
        var priceGteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_min', 130000)) }};
        var priceLteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_max', 1000000)) }};
    </script>
@stop

@php
    $hideManufacturer = $hideManufacturer ?? false;
@endphp

<div class="space-y-4">

    {!! \App\View\FragmentCache::categoryTree() !!}

    <form method="get" action="{{ request()->url() }}">
        @foreach (collect(request()->query())->except(['filter', 'page'])->dot() as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        {!! \App\View\FragmentCache::facets((bool) ($hideManufacturer ?? false)) !!}
    </form>

    @if (count($latestProducts ?? []) > 0)
        <div class="rounded-2xl bg-white border border-gray-200 p-4">
            <h5 class="text-[15px] font-bold tracking-tight mb-3">Sản phẩm mới</h5>
            <div class="space-y-3">
                @foreach ($latestProducts as $product)
                    <div class="flex gap-3">
                        <a href="{!! $product->url !!}" title="{!! $product->name !!}" class="shrink-0">
                            <img src="{{ $product->thumbnail(50, 50) }}" alt="{{ $product->name }}"
                                class="w-12 h-12 rounded-lg object-cover border border-gray-100">
                        </a>
                        <div class="min-w-0 flex-1">
                            <a href="{!! $product->url !!}" title="{!! $product->name !!}"
                                class="block text-[13px] font-medium leading-snug line-clamp-2 hover:text-gray-900 transition">
                                {!! $product->name !!}
                            </a>
                            @if (filled($product->productVariantSpecial))
                                <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                    <span
                                        class="text-[13px] font-semibold">{!! $product->productVariantSpecial->pricePromotionLabel !!}</span>
                                    <span
                                        class="text-[11.5px] text-gray-400 line-through">{!! $product->productVariantSpecial->priceRegularLabel !!}</span>
                                </div>
                            @else
                                <p class="mt-1 text-[13px] font-semibold">{!! $product->priceLabel !!}</p>
                            @endif
                            <p class="mt-0.5 text-[11.5px] text-amber-500">
                                {{ str_repeat('★', max(0, min(5, intval(round($product->ratingAvg))))) }}<span
                                    class="text-gray-300">{{ str_repeat('★', 5 - max(0, min(5, intval(round($product->ratingAvg))))) }}</span>
                                <span class="text-gray-400">({{ $product->reviewCount }})</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
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

            var rating = p.get('filter[rating_min]');
            if (rating !== null) {
                document.querySelectorAll('input[name="filter[rating_min]"]').forEach(function(rb) {
                    rb.checked = (rb.value === rating);
                });
            }

            var warehouse = p.get('filter[warehouse_id]');
            if (warehouse !== null) {
                document.querySelectorAll('input[name="filter[warehouse_id]"]').forEach(function(rb) {
                    rb.checked = (rb.value === warehouse);
                });
            }
        }
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

            tree.addEventListener('click', function(e) {
                var btn = e.target.closest('.cat-tree-toggle');
                if (!btn || !tree.contains(btn)) return;
                e.preventDefault();
                toggleNode(btn.closest('[data-cat-node]'));
            });

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
