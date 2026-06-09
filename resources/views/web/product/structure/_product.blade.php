@php
    $special = $product->productSpecial;
@endphp
<div class="col-lg-3 col-md-4 col-12 col-sm-6">
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
            <div class="align-self-center d-block d-lg-none">
                <div class="img-manufacture justify-content-center">
                    <img alt="{{ $product->manufacturer->name }}" src="{{ $product->manufacturer->thumbnail(90, 43) }}"
                        class="center-block d-block mx-auto" />
                </div>
            </div>
        @endif

        <div class="product-content-wrap">
            <div class="product-category">
                @foreach ($product->categories as $cat)
                    <span class="badge badge-pill text-wrap text-secondary ps-0">
                        {{ $cat->title }}
                    </span>
                @endforeach
            </div>

            @if (count($product->matchedFilterNames))
                <div class="product-filter">
                    <span class="text-secondary text-decoration-underline fst-italic">
                        {{ implode(', ', $product->matchedFilterNames) }}
                    </span>
                </div>
            @endif

            <h2>
                <a href="{{ $product->url }}" title="{{ $product->name }}">{{ $product->name }}</a>
            </h2>

            @if ($product->manufacturer)
                <div class="manufacturer">
                    <span class="text-secondary">{{ $product->manufacturer->name }}</span>
                </div>
            @endif

            <div class="product-rate-cover">
                <div class="product-rate d-inline-block">
                    <img src="/client/images/stars-{{ (int) round($product->ratingAvg) }}.png"
                        alt="{{ $product->reviewCount }} đánh giá" />
                </div>
                <span class="font-small ml-5 text-muted">({{ (int) round($product->ratingAvg) }})</span>
            </div>

            @if ($product->weight && $product->weight > 0)
                <div>
                    <span class="font-small text-muted">{{ $product->weight }}</span>
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
                <div class="countdown-price d-flex justify-content-center mt-3">
                    <span class="text-light btn-brand px-2 rounded" id="countdown_{{ $product->id }}"></span>
                </div>
                <script type="text/javascript">
                    setInterval("countDownTime('{{ $special->dateEnd }}', '{{ $product->id }}')", 550);
                </script>
            @endif
        </div>
    </div>
</div>
