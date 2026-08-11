<style>
    .footer-mid,
    .infun-footer-bottom {
        background-color: #FBF6F1;
        border-top: 1px solid rgba(229, 231, 235, .8);
    }

    .footer-mid {
        padding-top: 4rem;
        padding-bottom: 4rem;
    }

    .infun-footer-bottom-inner {
        padding-top: 1.5rem;
        padding-bottom: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    @media (min-width: 640px) {
        .infun-footer-bottom-inner {
            flex-direction: row;
            justify-content: space-between;
        }
    }

    .infun-footer-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }

    @media (min-width: 768px) {
        .infun-footer-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 1024px) {
        .infun-footer-grid {
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 2rem;
        }
    }

    .footer-mid .content-company-infunstudio .logo img {
        height: 3.5rem;
        width: auto;
        max-width: 100%;
        object-fit: contain;
        margin-bottom: 1rem;
    }

    .footer-mid .content-company-infunstudio p {
        max-width: 20rem;
        font-size: .875rem;
        line-height: 1.6;
        color: #4b5563;
    }

    .footer-mid .footer-link-widget .widget-title,
    .footer-mid .infun-footer-fb .widget-title {
        margin-bottom: 1rem;
        font-size: .8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #111827;
    }

    .footer-mid .footer-link-widget .contact-infor,
    .footer-mid .footer-link-widget .footer-list {
        display: flex;
        flex-direction: column;
        gap: .65rem;
    }

    .footer-mid .footer-link-widget .contact-infor li,
    .footer-mid .footer-link-widget .footer-list li {
        font-size: .875rem;
        color: #4b5563;
        margin: 0;
    }

    .footer-mid .footer-link-widget .footer-list li:hover {
        padding-left: 0;
    }

    .footer-mid .footer-link-widget .footer-list li a,
    .footer-mid .footer-link-widget .contact-infor a {
        font-size: .875rem;
        color: #4b5563;
        transition: color .15s ease;
    }

    .footer-mid .footer-link-widget .footer-list li a:hover,
    .footer-mid .footer-link-widget .contact-infor a:hover {
        color: #e88a5e;
    }

    .infun-footer-fb-card {
        border: 1px solid #f1f1f1;
        background: #fff;
        border-radius: .75rem;
        padding: 1rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
    }

    .infun-social-btn {
        display: flex;
        height: 2.25rem;
        width: 2.25rem;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #6b7280;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .infun-social-btn:hover {
        background: #f4a883;
        border-color: #f4a883;
        color: #fff;
    }

    .infun-footer-locale-btn {
        display: flex;
        align-items: center;
        gap: .375rem;
        color: #6b7280;
        transition: color .15s ease;
    }

    .infun-footer-locale-btn:hover {
        color: #111827;
    }
</style>
<footer class="main">
    @yield('footer_top')
    <section class="footer-mid">
        <div class="container mx-auto max-w-7xl px-4">
            <div class="infun-footer-grid">
                <div class="content-company-infunstudio list-menu-ft">
                    <div class="logo">
                        <a class="mb-4 block" href="/">
                            <img src="{!! thumbnail(getConfigDb('config_logo')) !!}" alt="{!! getConfigDb('seo_title_home') !!}">
                        </a>
                    </div>
                    {!! getConfigDb('config_footer1') !!}
                </div>
                <div class="footer-link-widget">
                    <div class="block-menu-footer list-menu-ft mb-8">
                        {!! getConfigDb('config_footer2') !!}
                    </div>
                </div>
                <div class="footer-link-widget">
                    <div class="block-menu-footer list-menu-ft">
                        {!! getConfigDb('config_footer3') !!}
                    </div>
                </div>
                <div class="infun-footer-fb">
                    <div class="block-menu-footer list-menu-ft infun-footer-fb-card">
                        {!! getConfigDb('config_footer4') !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="infun-footer-bottom">
        <div class="container mx-auto max-w-7xl px-4">
            <div class="infun-footer-bottom-inner">
                <p class="text-xs text-gray-500">
                    © {{ date('Y') }} {{ getConfigDb('config_name') ?: 'In&Fun Studio' }}. All rights reserved.
                </p>

                <div class="flex items-center gap-4 text-xs">
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="infun-footer-locale-btn">
                            <i class="fi fi-rs-globe"></i>
                            <span class="uppercase">{{ $currentLocale }}</span>
                        </button>
                        <div class="absolute bottom-full right-0 z-50 mb-2 w-44 rounded-lg border border-gray-100 bg-white pb-2 pt-3 shadow-lg"
                            x-show="open" x-cloak style="display:none"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0">
                            <ul class="m-0 list-none p-0">
                                @foreach ($languages as $language)
                                    @php $languageCode = strtolower((string) ($language->code ?? '')); @endphp
                                    <li>
                                        <a href="{{ route('locale.language', $language->code) }}"
                                            data-lang="{{ $language->code }}"
                                            class="flex items-center gap-2 whitespace-nowrap px-4 py-2 hover:bg-gray-50 {{ $languageCode === $currentLocale ? 'font-semibold text-brand' : '' }}">
                                            @if (!empty($language->image))
                                                <img src="{{ $language->image }}" alt="" width="18" height="12"
                                                    class="flex-shrink-0">
                                            @endif
                                            <span>{{ $language->title ?? ($language->name ?? strtoupper((string) ($language->code ?? ''))) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="infun-footer-locale-btn">
                            <i class="fi fi-rs-coins"></i>
                            <span>{{ $currencySymbol }} {{ $currentCurrency->code }}</span>
                        </button>
                        <div class="absolute bottom-full right-0 z-50 mb-2 w-52 rounded-lg border border-gray-100 bg-white pb-2 pt-3 shadow-lg"
                            x-show="open" x-cloak style="display:none"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0">
                            <ul class="m-0 list-none p-0">
                                @foreach ($currencies as $currency)
                                    @php $symbol = trim((string) ($currency->symbol_right ?? '')) ?: trim((string) ($currency->symbol_left ?? '')); @endphp
                                    <li>
                                        <a href="{{ route('locale.currency', $currency->code) }}"
                                            data-currency="{{ $currency->code }}"
                                            class="flex items-center gap-2 whitespace-nowrap px-4 py-2 hover:bg-gray-50 {{ strtoupper((string) $currency->code) === strtoupper((string) $currentCurrency->code) ? 'font-semibold text-brand' : '' }}">
                                            <span class="inline-block w-5 flex-shrink-0 text-center">{{ $symbol }}</span>
                                            <span class="flex-shrink-0">{{ $currency->code }}</span>
                                            <span class="ml-auto truncate text-sm text-gray-400">{{ $currency->title }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @foreach ([
        ['url' => getConfigDb('config_facebook'), 'label' => 'Facebook', 'path' => 'M13.5 9H15V6.5h-1.5C11.6 6.5 10 8.1 10 10.2V12H8v2.5h2V19h2.5v-4.5H15l.5-2.5h-3v-1.6c0-.6.4-1 1-1Z'],
        [
            'url' => getConfigDb('config_instagram'),
            'label' => 'Instagram',
            'path' =>
                'M12 8.7a3.3 3.3 0 1 0 0 6.6 3.3 3.3 0 0 0 0-6.6ZM12 6c-1.7 0-1.9 0-2.6.05-.66.03-1.1.13-1.5.29a3 3 0 0 0-1.08.7 3 3 0 0 0-.7 1.08c-.16.4-.26.84-.29 1.5C6 10.1 6 10.3 6 12s0 1.9.05 2.6c.03.66.13 1.1.29 1.5a3 3 0 0 0 .7 1.08c.31.31.68.55 1.08.7.4.16.84.26 1.5.29.7.05.9.05 2.6.05s1.9 0 2.6-.05c.66-.03 1.1-.13 1.5-.29a3 3 0 0 0 1.08-.7c.31-.31.55-.68.7-1.08.16-.4.26-.84.29-1.5.05-.7.05-.9.05-2.6s0-1.9-.05-2.6a4.6 4.6 0 0 0-.29-1.5 3 3 0 0 0-.7-1.08 3 3 0 0 0-1.08-.7 4.6 4.6 0 0 0-1.5-.29C13.9 6 13.7 6 12 6Zm0 1.35c.28 0 1 0 1.5.02.62.02.96.12 1.18.2.3.11.51.25.74.48.23.23.37.44.48.74.08.22.18.56.2 1.18.02.5.02.72.02 1.5s0 1-.02 1.5c-.02.62-.12.96-.2 1.18a2 2 0 0 1-.48.74 2 2 0 0 1-.74.48c-.22.08-.56.18-1.18.2-.5.02-.72.02-1.5.02s-1 0-1.5-.02c-.62-.02-.96-.12-1.18-.2a2 2 0 0 1-.74-.48 2 2 0 0 1-.48-.74c-.08-.22-.18-.56-.2-1.18-.02-.5-.02-.72-.02-1.5s0-1 .02-1.5c.02-.62.12-.96.2-1.18.11-.3.25-.51.48-.74.23-.23.44-.37.74-.48.22-.08.56-.18 1.18-.2.5-.02.72-.02 1.5-.02Zm3.9-.9a.78.78 0 1 0 0 1.56.78.78 0 0 0 0-1.56Z',
        ],
        ['url' => getConfigDb('config_tiktok'), 'label' => 'TikTok', 'path' => 'M15.5 5h-2.2v9.6a2.1 2.1 0 1 1-1.5-2v-2.2a4.3 4.3 0 1 0 3.7 4.3V9.4c.8.6 1.8.9 2.8.9V8.1c-1.6 0-2.8-1.2-2.8-2.8V5Z'],
        ['url' => getConfigDb('config_threads'), 'label' => 'Threads', 'path' => 'M12 6c-3.3 0-5.6 2-5.9 5.1h1.7c.3-2.2 1.8-3.5 4.2-3.5 2.5 0 4 1.4 4 3.6 0 1.6-.8 2.7-2.2 3.3.1-.4.1-.8.1-1.2 0-2-1.4-3.3-3.6-3.3-2.1 0-3.6 1.2-3.6 3 0 1.9 1.6 3.1 3.9 3.1 1.3 0 2.4-.3 3.2-.9.9.6 2 .9 3.3.9v-1.6c-.7 0-1.3-.1-1.8-.4 1.2-.9 1.8-2.2 1.8-3.9C17.1 8 14.9 6 12 6Zm-.4 8.6c-1.3 0-2.1-.6-2.1-1.5 0-.9.8-1.5 2-1.5 1.2 0 1.9.6 1.9 1.6v.2c-.5.8-1 1.2-1.8 1.2Z'],
    ] as $social)
                        @continue(empty($social['url']))
                        <a href="{{ $social['url'] }}" title="{{ $social['label'] }}" target="_blank" rel="noopener"
                            class="infun-social-btn">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="{{ $social['path'] }}" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</footer>
<div id="link-cart" style="display: none;"></div>
<div id="dialog-confirm"></div>
<div id="dialog-consult-sign"></div>
<div id="dialog-confirm-wishlist" class="text-center"></div>
<div id="dialog-confirm-wishlist-nlg" class="text-center"></div>
<div id="fb-root"></div>
<script type="text/javascript">
    var urlAccountWishlist = '{{ route('account.wishlist') }}';
    var urlAccountLogin = '{{ route('auth.login') }}';
    (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s);
        js.id = id;
        js.async = true;
        js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.3&appId=156028201458706";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));
</script>
@include('web::share._legacy_scripts')
