@php
    $special = $product->productVariantSpecial;
    $discountPercent = ($product->hasVariants && $product->maxVariantDiscountPercent > 0)
        ? $product->maxVariantDiscountPercent
        : ($special->discountPercent ?? null);
@endphp
<a href="{{ $product->url }}" title="{{ $product->name }}"
    class="group bg-white border border-line rounded-2xl p-2.5 hover:border-ink hover:-translate-y-0.5 transition flex flex-col">
    <div class="relative aspect-square rounded-xl overflow-hidden bg-line mb-2.5">
        <img src="{{ $product->thumbnail(400, 400) }}" alt="{{ $product->name }}" class="w-full h-full object-cover"
            loading="lazy">
        @if ($discountPercent)
            <span
                class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-brand text-white text-[11px] font-bold">-{{ $discountPercent }}%</span>
        @endif
        @unless ($product->inStock)
            <span
                class="absolute inset-0 bg-white/70 grid place-items-center text-[13px] font-semibold text-ink-2">{{ $product->stockLabel }}</span>
        @endunless
    </div>

    @if ($product->manufacturer)
        <p class="text-[12px] font-semibold text-ink-3 tracking-wide truncate">{{ $product->manufacturer->name }}</p>
    @endif

    <p class="text-[13.5px] leading-snug line-clamp-2 min-h-[38px] mt-0.5 group-hover:text-brand transition">
        {{ $product->name }}
    </p>

    <div class="mt-2 flex items-baseline gap-2 flex-wrap">
        @if ($special)
            <span class="text-[15px] font-bold text-brand">{!! $special->pricePromotionLabel !!}</span>
            <span class="text-[12px] text-ink-3 line-through">{!! $special->priceRegularLabel !!}</span>
        @else
            <span class="text-[15px] font-bold {{ $discountPercent ? 'text-brand' : '' }}">{!! $product->priceLabel !!}</span>
        @endif
    </div>

    @if ($product->reviewCount > 0)
        <p class="mt-0.5 text-[12px] text-ink-3">
            ★ {{ number_format($product->ratingAvg, 1) }} · {{ number_format($product->reviewCount) }} đánh giá
        </p>
    @else
        <p class="mt-0.5 text-[12px] text-ink-3">Chưa có đánh giá</p>
    @endif

    @if ($special && $special->dateEnd)
        <div class="mt-2">
            <span class="text-white bg-ink text-[11px] font-semibold px-2 py-1 rounded-lg"
                id="countdown_{{ $product->id }}"></span>
        </div>
        <script type="text/javascript">
            setInterval("countDownTime('{{ $special->dateEnd }}', '{{ $product->id }}')", 550);
        </script>
    @endif
</a>
