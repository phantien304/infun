@if (count($features))
    @php $customCtaThreshold = 8; @endphp
    <section class="section-padding product-feature">
        <div class="container mx-auto max-w-7xl px-4">
            <div class="mb-10 text-center">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-brand">Xưởng gia công</p>
                <h3 class="text-2xl font-bold text-gray-900 sm:text-3xl">Sản phẩm nổi bật</h3>
            </div>

            <div class="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 lg:gap-x-8">
                @foreach ($features as $product)
                    <a href="{{ $product->url }}" title="{{ $product->name }}" class="group block">
                        <figure class="aspect-square overflow-hidden rounded-lg bg-gray-50">
                            <img src="{{ $product->thumbnail(500, 500) }}" alt="{{ $product->name }}"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://picsum.photos/seed/p{{ $product->id }}/500/500'"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        </figure>
                        <div class="mt-4">
                            <h3 class="mb-1 line-clamp-2 text-sm text-gray-800 transition group-hover:text-brand">
                                {{ $product->name }}
                            </h3>
                            <p class="text-sm font-semibold text-gray-900">{{ $product->priceLabel }}</p>
                        </div>
                    </a>
                @endforeach

                @if (count($features) < $customCtaThreshold)
                    <a href="{{ route('contact.index') }}" title="Đặt thiết kế theo yêu cầu" class="group block">
                        <div class="flex aspect-square flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-200 bg-gray-50/60 p-3 text-center transition group-hover:border-brand group-hover:bg-brand-50">
                            <svg class="h-7 w-7 text-gray-400 transition group-hover:text-brand" fill="none"
                                stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                            </svg>
                            <span class="text-sm font-semibold text-gray-700 group-hover:text-brand">
                                Đặt thiết kế<br>theo yêu cầu
                            </span>
                        </div>
                    </a>
                @endif
            </div>
        </div>
    </section>
@endif
