@php
    $special = $product->productVariantSpecial;
@endphp
<div class="product-cart-wrap mb-30">
    <div class="product-img-action-wrap">
        <div class="product-img product-img-zoom">
            <a href="{{ $product->url }}" title="{{ $product->name }}">
                <img src="{{ $product->thumbnail(300, 300) }}" alt="{{ $product->name }}" class="default-img">
                <img src="{{ $product->thumbnail(300, 300) }}" alt="{{ $product->name }}" class="hover-img">
            </a>
        </div>
        @if ($product->hasVariants && $product->maxVariantDiscountPercent > 0)
            <div class="product-badges product-badges-position product-badges-mrg">
                <span class="sale">-{{ $product->maxVariantDiscountPercent }}%</span>
            </div>
        @elseif ($special && $special->discountPercent)
            <div class="product-badges product-badges-position product-badges-mrg">
                <span class="sale">-{{ $special->discountPercent }}%</span>
            </div>
        @elseif (filled($product->badge))
            <div class="product-badges product-badges-position product-badges-mrg">
                <span class="{{ $product->badge }}">
                    {!! getModuleConfig('product.badge.' . $product->badge) !!}
                </span>
            </div>
        @endif
    </div>

    @if ($product->manufacturer && $product->manufacturer?->image)
        <div class="self-center block lg:hidden">
            <div class="img-manufacture flex justify-center">
                <img alt="{{ $product->manufacturer->name }}" src="{{ $product->manufacturer->thumbnail(90, 43) }}"
                    class="block mx-auto" />
            </div>
        </div>
    @endif

    <div class="product-content-wrap">
        <div class="product-category">
            @foreach ($product->categories as $cat)
                <span
                    class="inline-block px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 whitespace-normal mr-1 mb-1">
                    {{ $cat->title }}
                </span>
            @endforeach
        </div>

        @if (count($product->matchedFilterNames))
            <div class="product-filter mt-1">
                <span class="text-gray-500 underline italic text-xs">
                    {{ implode(', ', $product->matchedFilterNames) }}
                </span>
            </div>
        @endif

        <h2>
            <a href="{{ $product->url }}" title="{{ $product->name }}">{{ $product->name }}</a>
        </h2>

        @if ($product->manufacturer)
            <div class="manufacturer">
                <span class="text-gray-500">{{ $product->manufacturer?->name }}</span>
            </div>
        @endif

        @php
            $ratingFloat = (float) $product->ratingAvg;
            $ratingPct = max(0, min(100, $ratingFloat * 20));
        @endphp
        <div class="product-rate-cover flex items-center gap-1">
            <span class="relative inline-block leading-none tracking-[2px] text-sm">
                <span class="text-gray-300">
                    <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                    <i class="fa fa-star"></i><i class="fa fa-star"></i>
                </span>
                <span class="absolute top-0 left-0 overflow-hidden whitespace-nowrap text-[#ee4d2d]"
                    style="width: {{ $ratingPct }}%;">
                    <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                    <i class="fa fa-star"></i><i class="fa fa-star"></i>
                </span>
            </span>
            <span class="text-xs text-gray-500">
                {{ number_format($ratingFloat, 1) }}
                @if ($product->reviewCount > 0)
                    <span class="text-gray-400">({{ $product->reviewCount }})</span>
                @endif
            </span>
        </div>

        @if ($product->weight && $product->weight > 0)
            <div>
                <span class="font-small text-muted">{{ (int) $product->weight . $product->weightUnit }}</span>
            </div>
        @endif

        <div class="product-price">
            @if ($special)
                <span>{{ $special->pricePromotionLabel }}</span>
                <span class="old-price">{{ $special->priceRegularLabel }}</span>
            @else
                <span>{{ $product->priceLabel }}</span>
            @endif
        </div>

        @if ($special && $special->dateEnd)
            <div class="countdown-price flex justify-center mt-3">
                <span class="text-white btn-brand px-2 rounded" id="countdown_{{ $product->id }}"></span>
            </div>
            <script type="text/javascript">
                setInterval("countDownTime('{{ $special->dateEnd }}', '{{ $product->id }}')", 550);
            </script>
        @endif
    </div>
</div>
