@extends('web::layouts.main')

@section('meta')
    <meta property="og:site_name" content="{{ getConfigDb('config_name') }}" />
    <meta property="og:type" content="website" />
    <meta property="og:url" itemprop="url" content="{{ route('home') }}" />
    <meta content="{{ $titleSeo }}" itemprop="headline" property="og:title" />
    <meta content="{{ $descriptionSeo }}" itemprop="description" property="og:description" />
    @include('web::share.structure._meta_common')
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="{{ $titleSeo }}" />
    <meta name="twitter:description" content="{{ $descriptionSeo }}" />
@endsection

@section('content')
    @php
        $ph = fn(string $seed, int $w, int $h) => "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
        $img = fn(?string $src, string $seed, int $w, int $h) => filled($src) ? $src : $ph($seed, $w, $h);
        $fb = fn(string $seed, int $w, int $h) => "this.onerror=null;this.src='" . $ph($seed, $w, $h) . "'";

        $categories = collect($homeCategories ?? []);
        $flash = collect($flashSaleProducts ?? []);
        $latest = collect($latestProducts ?? []);
        $featureList = collect($features ?? []);
        $reviews = collect($storeReviews ?? []);
        $blogList = collect($blogs ?? []);

        $heroSlide = null;
        foreach ($banners as $banner) {
            foreach ($banner->bannerValues as $value) {
                $heroSlide = $value;
                break 2;
            }
        }
    @endphp

    <div class="mx-auto max-w-[1320px] px-5">
        <section data-test="home-hero" class="pt-6 pb-4 grid grid-cols-12 gap-3 auto-rows-[152px]">

            <a href="{{ route('product.getList') }}" data-test="hero-main"
                class="col-span-12 lg:col-span-7 row-span-2 relative rounded-3xl overflow-hidden group bg-ink">
                <img src="{{ $img($heroSlide->image ?? null, 'aurora-hero', 1200, 700) }}" alt=""
                    onerror="{{ $fb('aurora-hero', 1200, 700) }}"
                    class="absolute inset-0 w-full h-full object-cover opacity-70">
                <div class="absolute inset-0 bg-gradient-to-tr from-ink/85 via-ink/45 to-transparent"></div>
                <div class="relative h-full p-8 lg:p-10 flex flex-col justify-end text-white">
                    <span
                        class="w-fit mb-4 px-3 py-1 rounded-full bg-white/15 backdrop-blur text-[12px] font-semibold tracking-wide">
                        BỘ SƯU TẬP MỚI
                    </span>
                    <h2 class="text-[38px] lg:text-[52px] leading-[1.02] font-extrabold tracking-tight max-w-[15ch]">
                        {!! $heroSlide && isset($heroSlide->description)
                            ? $heroSlide->description->title
                            : 'Mọi thứ bạn cần, một nơi duy nhất' !!}
                    </h2>
                    <p class="mt-3 text-white/75 text-[15px] max-w-[42ch]">
                        {!! $heroSlide && isset($heroSlide->description)
                            ? $heroSlide->description->content
                            : 'Hàng nghìn sản phẩm từ các thương hiệu uy tín, giao trong 24 giờ nội thành.' !!}
                    </p>
                    <span
                        class="mt-6 w-fit h-11 px-6 rounded-full bg-white text-ink font-semibold text-[15px] grid place-items-center group-hover:bg-brand group-hover:text-white transition">
                        Khám phá ngay
                    </span>
                </div>
            </a>
            <a href="{{ route('product.special') }}" data-test="hero-promo" x-data="{
                left: 2 * 3600 + 47 * 60 + 13,
                init() { setInterval(() => this.left > 0 && this.left--, 1000) },
                get hh() { return String(Math.floor(this.left / 3600)).padStart(2, '0') },
                get mm() { return String(Math.floor(this.left % 3600 / 60)).padStart(2, '0') },
                get ss() { return String(this.left % 60).padStart(2, '0') }
            }"
                class="col-span-6 lg:col-span-5 row-span-1 rounded-3xl bg-brand text-white p-6 flex flex-col justify-between group">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[13px] font-semibold text-white/80">GIỜ VÀNG</p>
                        <p class="text-[26px] font-extrabold leading-tight mt-0.5">Giảm đến 50%</p>
                    </div>
                    <svg class="w-6 h-6 opacity-60 group-hover:translate-x-1 transition" fill="none"
                        stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[13px] text-white/80 mr-1">Kết thúc sau</span>
                    <span class="h-9 w-11 rounded-xl bg-white/20 grid place-items-center font-bold tabular-nums text-[16px]"
                        x-text="hh">02</span>
                    <span class="font-bold opacity-60">:</span>
                    <span class="h-9 w-11 rounded-xl bg-white/20 grid place-items-center font-bold tabular-nums text-[16px]"
                        x-text="mm">47</span>
                    <span class="font-bold opacity-60">:</span>
                    <span class="h-9 w-11 rounded-xl bg-white/20 grid place-items-center font-bold tabular-nums text-[16px]"
                        x-text="ss">13</span>
                </div>
            </a>

            <a href="{{ route('product.getList') }}" data-test="hero-voucher"
                class="col-span-6 lg:col-span-2 row-span-1 rounded-3xl bg-mint-tint p-5 flex flex-col justify-between hover:ring-2 hover:ring-mint transition">
                <svg class="w-7 h-7 text-mint" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path
                        d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z" />
                </svg>
                <div>
                    <p class="text-[22px] font-extrabold text-mint leading-none">-100K</p>
                    <p class="text-[13px] text-ink-2 mt-1 font-medium">Cho khách mới</p>
                </div>
            </a>

            <a href="{{ route('blog.getList') }}" data-test="hero-blog"
                class="col-span-6 lg:col-span-3 row-span-1 rounded-3xl overflow-hidden relative group bg-ink">
                <img src="{{ $blogList->isNotEmpty() ? $img($blogList->first()->thumbnail(500, 320), 'aurora-blog', 500, 320) : $ph('aurora-blog', 500, 320) }}"
                    alt="" onerror="{{ $fb('aurora-blog', 500, 320) }}"
                    class="absolute inset-0 w-full h-full object-cover opacity-75">
                <div class="absolute inset-0 bg-ink/35 group-hover:bg-ink/50 transition"></div>
                <div class="relative h-full p-5 flex flex-col justify-end text-white">
                    <p class="text-[12px] font-semibold text-white/80">VỪA LÊN KỆ</p>
                    <p class="text-[19px] font-bold leading-tight">{{ $latest->count() ?: $featureList->count() }} sản phẩm
                        mới</p>
                </div>
            </a>
        </section>

        @if ($categories->isNotEmpty())
            <section class="py-8" data-test="home-categories">
                <div class="flex items-end justify-between mb-4">
                    <h2 class="text-[24px] font-bold tracking-tight">Danh mục nổi bật</h2>
                    <a href="{{ route('product.getList') }}" class="text-[14px] font-medium text-ink-2 hover:text-ink">Xem
                        tất cả →</a>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-8 gap-3" data-test="category-grid"
                    data-test-count="{{ $categories->count() }}">
                    @foreach ($categories as $cat)
                        <a href="{!! $cat->url !!}" title="{{ $cat->title }}" data-test="category-card"
                            class="rounded-2xl bg-white border border-line p-4 flex flex-col items-center gap-3 hover:border-ink hover:-translate-y-0.5 transition">
                            <img src="{{ $img($cat->image, 'cat' . $loop->index, 120, 120) }}" alt="{{ $cat->title }}"
                                onerror="{{ $fb('cat' . $loop->index, 120, 120) }}" loading="lazy"
                                class="w-14 h-14 rounded-2xl object-cover">
                            <span
                                class="text-[13px] font-medium text-center leading-tight line-clamp-2">{{ $cat->title }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($flash->isNotEmpty())
            <section class="pb-8" data-test="home-flash">
                <div class="rounded-3xl bg-white border border-line p-5 lg:p-6" x-data="{
                    left: 2 * 3600 + 47 * 60 + 13,
                    init() { setInterval(() => this.left > 0 && this.left--, 1000) },
                    get hh() { return String(Math.floor(this.left / 3600)).padStart(2, '0') },
                    get mm() { return String(Math.floor(this.left % 3600 / 60)).padStart(2, '0') },
                    get ss() { return String(this.left % 60).padStart(2, '0') }
                }">
                    <div class="flex flex-wrap items-center gap-4 mb-5">
                        <h2 class="text-[24px] font-bold tracking-tight flex items-center gap-2">
                            <span class="text-brand">⚡</span> Flash sale
                        </h2>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="h-8 w-9 rounded-lg bg-ink text-white grid place-items-center text-[14px] font-bold tabular-nums"
                                x-text="hh">02</span>
                            <span class="font-bold text-ink-3">:</span>
                            <span
                                class="h-8 w-9 rounded-lg bg-ink text-white grid place-items-center text-[14px] font-bold tabular-nums"
                                x-text="mm">47</span>
                            <span class="font-bold text-ink-3">:</span>
                            <span
                                class="h-8 w-9 rounded-lg bg-ink text-white grid place-items-center text-[14px] font-bold tabular-nums"
                                x-text="ss">13</span>
                        </div>
                        <a href="{{ route('product.special') }}"
                            class="ml-auto text-[14px] font-medium text-ink-2 hover:text-ink">Xem tất cả →</a>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" data-test="flash-grid"
                        data-test-count="{{ $flash->count() }}">
                        @foreach ($flash->take(5) as $product)
                            @php $sold = 35 + ($product->id % 60); @endphp
                            <a href="{!! $product->url !!}" title="{{ $product->name }}" data-test="flash-card"
                                class="group">
                                <div class="relative aspect-square rounded-2xl overflow-hidden mb-2.5 bg-line">
                                    <img src="{{ $img($product->thumbnail(400, 400), 'f' . $loop->index, 400, 400) }}"
                                        alt="{{ $product->name }}" loading="lazy"
                                        onerror="{{ $fb('f' . $loop->index, 400, 400) }}"
                                        class="w-full h-full object-cover">
                                    @if (filled($product->productVariantSpecial))
                                        <span
                                            class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-brand text-white text-[11px] font-bold">GIẢM</span>
                                    @endif
                                </div>
                                <p class="text-[13.5px] leading-snug line-clamp-2 group-hover:text-brand transition">
                                    {{ $product->name }}</p>
                                <div class="mt-1 flex items-baseline gap-2 flex-wrap">
                                    @if (filled($product->productVariantSpecial))
                                        <span class="text-[15px] font-bold text-brand">{!! $product->productVariantSpecial->pricePromotionLabel !!}</span>
                                        <span class="text-[12.5px] text-ink-3 line-through">{!! $product->productVariantSpecial->priceRegularLabel !!}</span>
                                    @else
                                        <span class="text-[15px] font-bold text-brand">{!! $product->priceLabel !!}</span>
                                    @endif
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-brand-tint overflow-hidden">
                                    <div class="h-full bg-brand rounded-full" style="width: {{ $sold }}%"></div>
                                </div>
                                <p class="mt-1 text-[11.5px] text-ink-3">Đã bán {{ $sold }}%</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="pb-8 grid grid-cols-12 gap-3 auto-rows-[168px]" data-test="home-discover">

            <a href="{{ route('product.getList') }}"
                class="col-span-12 lg:col-span-4 row-span-2 rounded-3xl bg-ink text-white p-7 flex flex-col justify-between">
                <div>
                    <p class="text-[13px] font-semibold text-white/60">BÁN CHẠY NHẤT</p>
                    <h3 class="text-[28px] font-extrabold leading-tight mt-2">Sản phẩm nổi bật tuần này</h3>
                </div>
                <div class="flex -space-x-3">
                    @foreach ($featureList->take(3) as $p)
                        <img src="{{ $img($p->thumbnail(120, 120), 'top' . $loop->index, 120, 120) }}"
                            alt="{{ $p->name }}" onerror="{{ $fb('top' . $loop->index, 120, 120) }}"
                            loading="lazy" class="w-14 h-14 rounded-2xl ring-3 ring-ink object-cover">
                    @endforeach
                    @if ($featureList->count() > 3)
                        <div
                            class="w-14 h-14 rounded-2xl ring-3 ring-ink bg-white/10 grid place-items-center text-[13px] font-bold">
                            +{{ $featureList->count() - 3 }}
                        </div>
                    @endif
                </div>
            </a>

            @if ($reviews->isNotEmpty())
                <div
                    class="col-span-12 sm:col-span-6 lg:col-span-4 row-span-1 rounded-3xl bg-white border border-line p-6 flex flex-col justify-between">
                    <div class="flex gap-0.5 text-brand text-[15px]">★★★★★</div>
                    <p class="text-[15px] leading-relaxed text-ink-2">
                        {{ $reviews->count() }}+ khách hàng đã đánh giá và quay lại mua tiếp.
                    </p>
                    <div class="flex -space-x-2">
                        @foreach ($reviews->take(4) as $r)
                            <img src="{{ $img($r->thumbnail(80, 80), 'av' . $loop->index, 80, 80) }}"
                                alt="{{ $r->name }}" onerror="{{ $fb('av' . $loop->index, 80, 80) }}"
                                loading="lazy" class="w-8 h-8 rounded-full ring-2 ring-white object-cover">
                        @endforeach
                    </div>
                </div>
            @endif

            <a href="{{ route('product.getList') }}"
                class="col-span-6 lg:col-span-2 row-span-1 rounded-3xl bg-brand-tint p-5 flex flex-col justify-between hover:ring-2 hover:ring-brand transition">
                <svg class="w-7 h-7 text-brand" fill="none" stroke="currentColor" stroke-width="1.7"
                    viewBox="0 0 24 24">
                    <path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z" />
                    <circle cx="7" cy="18" r="1.8" />
                    <circle cx="17.5" cy="18" r="1.8" />
                </svg>
                <div>
                    <p class="text-[17px] font-bold leading-tight">Giao 24h</p>
                    <p class="text-[13px] text-ink-2 mt-0.5">Nội thành</p>
                </div>
            </a>

            <a href="{{ route('product.getList') }}"
                class="col-span-6 lg:col-span-2 row-span-1 rounded-3xl bg-white border border-line p-5 flex flex-col justify-between hover:border-ink transition">
                <svg class="w-7 h-7 text-ink" fill="none" stroke="currentColor" stroke-width="1.7"
                    viewBox="0 0 24 24">
                    <path d="M12 3 4 6v6c0 5 3.4 8.2 8 9 4.6-.8 8-4 8-9V6l-8-3Z" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
                <div>
                    <p class="text-[17px] font-bold leading-tight">Đổi trả 30 ngày</p>
                    <p class="text-[13px] text-ink-2 mt-0.5">Miễn phí</p>
                </div>
            </a>

            @if ($blogList->isNotEmpty())
                <a href="{!! $blogList->first()->url !!}" title="{{ $blogList->first()->title }}"
                    class="col-span-12 sm:col-span-6 lg:col-span-4 row-span-1 rounded-3xl overflow-hidden relative group bg-ink">
                    <img src="{{ $img($blogList->first()->thumbnail(600, 340), 'bd', 600, 340) }}" alt=""
                        onerror="{{ $fb('bd', 600, 340) }}" loading="lazy"
                        class="absolute inset-0 w-full h-full object-cover opacity-80">
                    <div class="absolute inset-0 bg-gradient-to-t from-ink/80 to-transparent"></div>
                    <div class="relative h-full p-6 flex flex-col justify-end text-white">
                        <p class="text-[12px] font-semibold text-white/70">CẨM NANG MUA SẮM</p>
                        <p class="text-[18px] font-bold leading-snug mt-1 line-clamp-2">{{ $blogList->first()->title }}
                        </p>
                    </div>
                </a>
            @endif

            <div
                class="col-span-12 lg:col-span-4 row-span-1 rounded-3xl bg-white border border-line p-6 flex flex-col justify-center gap-3">
                <p class="text-[17px] font-bold leading-tight">Nhận ưu đãi sớm nhất</p>
                <div class="flex gap-2">
                    <input type="email" placeholder="email@cua-ban.com" aria-label="Email"
                        class="flex-1 h-10 px-3.5 rounded-xl bg-canvas border border-line text-[14px] placeholder:text-ink-3 focus:outline-none focus:border-ink transition">
                    <button type="button"
                        class="h-10 px-4 rounded-xl bg-ink text-white text-[14px] font-semibold hover:bg-ink-2 transition">Gửi</button>
                </div>
            </div>
        </section>
        @php $suggest = $latest->isNotEmpty() ? $latest : $featureList; @endphp
        @if ($suggest->isNotEmpty())
            <section class="pb-10" data-test="home-suggest" x-data="{ tab: 'all' }">
                <div class="flex items-end justify-between mb-4 gap-3 flex-wrap">
                    <h2 class="text-[24px] font-bold tracking-tight">Gợi ý cho bạn</h2>
                    <div class="flex gap-1.5">
                        @foreach ([['all', 'Tất cả'], ['sale', 'Đang giảm'], ['top', 'Đánh giá cao']] as [$key, $label])
                            <button type="button" @click="tab = '{{ $key }}'"
                                class="h-8 px-3.5 rounded-full text-[13px] font-medium transition"
                                :class="tab === '{{ $key }}' ? 'bg-ink text-white' :
                                    'bg-white border border-line hover:border-ink'">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" data-test="suggest-grid"
                    data-test-count="{{ $suggest->count() }}">
                    @foreach ($suggest as $product)
                        @php
                            $onSale = filled($product->productVariantSpecial);
                            $topRated = $product->ratingAvg >= 4;
                        @endphp
                        <a href="{!! $product->url !!}" title="{{ $product->name }}" data-test="suggest-card"
                            x-show="tab === 'all'
                                @if ($onSale) || tab === 'sale' @endif
                                @if ($topRated) || tab === 'top' @endif"
                            class="group bg-white border border-line rounded-2xl p-2.5 hover:border-ink hover:-translate-y-0.5 transition">
                            <div class="aspect-square rounded-xl overflow-hidden mb-2.5 bg-line">
                                <img src="{{ $img($product->thumbnail(400, 400), 's' . $loop->index, 400, 400) }}"
                                    alt="{{ $product->name }}" loading="lazy"
                                    onerror="{{ $fb('s' . $loop->index, 400, 400) }}" class="w-full h-full object-cover">
                            </div>
                            <p
                                class="text-[13.5px] leading-snug line-clamp-2 min-h-[38px] group-hover:text-brand transition">
                                {{ $product->name }}
                            </p>
                            <div class="mt-2 flex items-baseline gap-2 flex-wrap">
                                @if ($onSale)
                                    <span class="text-[15px] font-bold text-brand">{!! $product->productVariantSpecial->pricePromotionLabel !!}</span>
                                    <span class="text-[12px] text-ink-3 line-through">{!! $product->productVariantSpecial->priceRegularLabel !!}</span>
                                @else
                                    <span class="text-[15px] font-bold">{!! $product->priceLabel !!}</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-[12px] text-ink-3">
                                @if ($product->reviewCount > 0)
                                    ★ {{ number_format($product->ratingAvg, 1) }} ·
                                    {{ number_format($product->reviewCount) }} đánh giá
                                @else
                                    Chưa có đánh giá
                                @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        @if ($blogList->isNotEmpty())
            <section class="pb-12" data-test="home-blog">
                <div class="flex items-end justify-between mb-4">
                    <h2 class="text-[24px] font-bold tracking-tight">Chia sẻ</h2>
                    <a href="{{ route('blog.getList') }}" class="text-[14px] font-medium text-ink-2 hover:text-ink">Xem
                        tất cả →</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" data-test="blog-grid"
                    data-test-count="{{ $blogList->count() }}">
                    @foreach ($blogList as $item)
                        <a href="{!! $item->url !!}" title="{{ $item->title }}" data-test="blog-card"
                            class="group bg-white border border-line rounded-2xl overflow-hidden hover:border-ink hover:-translate-y-0.5 transition">
                            <div class="aspect-[4/3] overflow-hidden bg-line">
                                <img src="{{ $img($item->thumbnail(635, 420), 'b' . $loop->index, 635, 420) }}"
                                    alt="{{ $item->title }}" loading="lazy"
                                    onerror="{{ $fb('b' . $loop->index, 635, 420) }}" class="w-full h-full object-cover">
                            </div>
                            <div class="p-4">
                                <p
                                    class="text-[15px] font-semibold leading-snug line-clamp-2 group-hover:text-brand transition">
                                    {{ $item->title }}
                                </p>
                                <p class="text-[12.5px] text-ink-3 mt-2">{!! $item->publishedDate !!} · {{ $item->viewed }}
                                    lượt xem</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
